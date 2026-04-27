<?php

declare(strict_types=1);

use App\Models\User;

it('rejeita anônimo com 401', function (): void {
    $this->getJson('/api/location/countries')->assertUnauthorized();
});

it('lista todos os países do enum', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/location/countries')->assertOk();

    $codes = collect($response->json())->pluck('code')->all();

    expect($codes)
        ->toContain('BR', 'US', 'ES', 'AR', 'CA', 'MX')
        ->and(count($codes))->toBeGreaterThanOrEqual(20);
});

it('marca BR, US e ES como supports_lookup=true', function (): void {
    $user = User::factory()->create();

    $countries = collect(
        $this->actingAs($user)->getJson('/api/location/countries')->json()
    )->keyBy('code');

    expect($countries->get('BR')['supports_lookup'])->toBeTrue();
    expect($countries->get('US')['supports_lookup'])->toBeTrue();
    expect($countries->get('ES')['supports_lookup'])->toBeTrue();
    expect($countries->get('AR')['supports_lookup'])->toBeFalse();
    expect($countries->get('CA')['supports_lookup'])->toBeFalse();
});

it('inclui nomes localizados em pt, en e es', function (): void {
    $user = User::factory()->create();

    $br = collect(
        $this->actingAs($user)->getJson('/api/location/countries')->json()
    )->firstWhere('code', 'BR');

    expect($br['name_pt'])->toBe('Brasil');
    expect($br['name_en'])->toBe('Brazil');
    expect($br['name_es'])->toBe('Brasil');
});
