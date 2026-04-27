<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('location-lookup');
});

it('rejeita usuário não autenticado', function (): void {
    $this->postJson('/api/location/lookup', [
        'country_code' => 'BR',
        'postal_code' => '01310-100',
    ])->assertUnauthorized();
});

it('busca endereço brasileiro via ViaCEP', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response([
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310-100',
        ])
        ->assertOk()
        ->assertJsonPath('country_code', 'BR')
        ->assertJsonPath('state_code', 'SP')
        ->assertJsonPath('state_name', 'São Paulo')
        ->assertJsonPath('city', 'São Paulo')
        ->assertJsonPath('source', 'viacep');
});

it('normaliza CEP com hífen antes de consultar', function (): void {
    Http::fake([
        'viacep.com.br/ws/01310100/json/' => Http::response([
            'cep' => '01310-100',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310-100',
        ])
        ->assertOk()
        ->assertJsonPath('postal_code', '01310100');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/01310100/'));
});

it('busca ZIP americano via zippopotam', function (): void {
    Http::fake([
        'api.zippopotam.us/*' => Http::response([
            'country' => 'United States',
            'country abbreviation' => 'US',
            'places' => [[
                'place name' => 'Beverly Hills',
                'state' => 'California',
                'state abbreviation' => 'CA',
            ]],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'US',
            'postal_code' => '90210',
        ])
        ->assertOk()
        ->assertJsonPath('country_code', 'US')
        ->assertJsonPath('state_code', 'CA')
        ->assertJsonPath('city', 'Beverly Hills')
        ->assertJsonPath('source', 'zippopotam');
});

it('busca código postal espanhol via zippopotam', function (): void {
    Http::fake([
        'api.zippopotam.us/es/*' => Http::response([
            'places' => [[
                'place name' => 'Madrid',
                'state' => 'Madrid',
                'state abbreviation' => 'MD',
            ]],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'ES',
            'postal_code' => '28013',
        ])
        ->assertOk()
        ->assertJsonPath('city', 'Madrid');
});

it('retorna 404 quando ViaCEP devolve erro', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '99999999',
        ])
        ->assertNotFound();
});

it('retorna 404 quando zippopotam devolve 404', function (): void {
    Http::fake([
        'api.zippopotam.us/*' => Http::response(null, 404),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'US',
            'postal_code' => '00000',
        ])
        ->assertNotFound();
});

it('retorna 422 para país fora do enum', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'ZZ',
            'postal_code' => '12345',
        ])
        ->assertUnprocessable();
});

it('retorna 422 para país sem suporte de lookup', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'AR',
            'postal_code' => 'C1407',
        ])
        ->assertUnprocessable();
});

it('retorna 422 para CEP com formato inválido', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => 'abc',
        ])
        ->assertUnprocessable();
});

it('regex inválido NÃO chama API externa', function (): void {
    Http::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => 'XX-9999',
        ])
        ->assertUnprocessable();

    Http::assertNothingSent();
});

it('rate limiter corta floods em 30/min/user', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response(['localidade' => 'X', 'uf' => 'SP']),
    ]);

    $user = User::factory()->create();

    // 30 lookups com CEPs distintos (negativo cache não captura)
    foreach (range(1, 30) as $i) {
        $cep = sprintf('%08d', 10000000 + $i);
        $this->actingAs($user)
            ->postJson('/api/location/lookup', [
                'country_code' => 'BR',
                'postal_code' => $cep,
            ])
            ->assertOk();
    }

    // 31º estoura
    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '20000001',
        ])
        ->assertStatus(429);
});

it('rate limiter conta por usuário (outro user não é afetado)', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response(['localidade' => 'X', 'uf' => 'SP']),
    ]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    foreach (range(1, 30) as $i) {
        $cep = sprintf('%08d', 10000000 + $i);
        $this->actingAs($userA)
            ->postJson('/api/location/lookup', [
                'country_code' => 'BR',
                'postal_code' => $cep,
            ])
            ->assertOk();
    }

    // userA estourou
    $this->actingAs($userA)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '20000001',
        ])
        ->assertStatus(429);

    // userB ainda passa
    $this->actingAs($userB)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '20000001',
        ])
        ->assertOk();
});

it('retorna 502 quando upstream falha', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response(null, 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310100',
        ])
        ->assertStatus(502);
});

it('cache evita chamada duplicada para mesmo CEP', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response([
            'localidade' => 'São Paulo',
            'uf' => 'SP',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310100',
        ])->assertOk();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310100',
        ])->assertOk();

    Http::assertSentCount(1);
});

it('cache negativo evita re-tentar CEP inexistente', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '99999999',
        ])->assertNotFound();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '99999999',
        ])->assertNotFound();

    Http::assertSentCount(1);
});

it('falha de transporte não é cacheada (permite retry)', function (): void {
    Http::fake([
        'viacep.com.br/*' => Http::response(null, 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310100',
        ])->assertStatus(502);

    $callsAfterFirst = count(Http::recorded());

    $this->actingAs($user)
        ->postJson('/api/location/lookup', [
            'country_code' => 'BR',
            'postal_code' => '01310100',
        ])->assertStatus(502);

    // Segunda tentativa precisa ter disparado novas chamadas — prova que
    // o erro 502 não foi cacheado.
    expect(count(Http::recorded()))->toBeGreaterThan($callsAfterFirst);
});
