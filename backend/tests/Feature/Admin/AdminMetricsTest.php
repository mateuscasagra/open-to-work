<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Profile;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-04-24 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('rejeita usuário não autenticado com 401', function (): void {
    $this->getJson('/api/admin/metrics')->assertUnauthorized();
});

it('rejeita usuário comum com 403', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->getJson('/api/admin/metrics')
        ->assertForbidden();
});

it('admin recebe estrutura completa', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/metrics')
        ->assertOk()
        ->assertJsonStructure([
            'totals' => [
                'users',
                'applications',
                'resumes',
                'active_users',
            ],
            'subscriptions' => [
                'active',
                'canceled',
                'cancellation_rate',
                'mrr_cents',
                'total_revenue_cents',
            ],
            'top_applicants' => [
                '*' => ['user_id', 'name', 'email', 'applications_count'],
            ],
            'by_location' => [
                'countries',
                'states',
                'cities',
                'without_location',
            ],
        ]);
});

it('conta total de usuários cadastrados (incluindo o admin)', function (): void {
    User::factory()->admin()->create();
    User::factory()->count(4)->create();

    $admin = User::query()->where('is_admin', true)->firstOrFail();

    $this->actingAs($admin)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('totals.users', 5);
});

it('conta total de candidaturas de todos os usuários', function (): void {
    $admin = User::factory()->admin()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    Application::factory()->for($u1)->count(3)->create();
    Application::factory()->for($u2)->count(2)->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('totals.applications', 5);
});

it('conta total de currículos criados', function (): void {
    $admin = User::factory()->admin()->create();
    $u1 = User::factory()->create();

    Resume::factory()->for($u1)->count(2)->create();
    Resume::factory()->for($admin)->count(1)->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('totals.resumes', 3);
});

it('considera ativo quem tem ao menos 3 candidaturas na última semana', function (): void {
    $admin = User::factory()->admin()->create();

    // Ativo: 3 candidaturas dentro da última semana
    $active = User::factory()->create();
    Application::factory()->for($active)->count(3)->create([
        'applied_at' => '2026-04-22 10:00:00',
    ]);

    // Não ativo: 2 candidaturas na semana
    $u2 = User::factory()->create();
    Application::factory()->for($u2)->count(2)->create([
        'applied_at' => '2026-04-22 10:00:00',
    ]);

    // Não ativo: 5 candidaturas porém todas fora da janela
    $u3 = User::factory()->create();
    Application::factory()->for($u3)->count(5)->create([
        'applied_at' => '2026-04-10 10:00:00',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('totals.active_users', 1);
});

it('janela de "última semana" é estritamente os últimos 7 dias', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    // 6 dias atrás → dentro
    Application::factory()->for($user)->create(['applied_at' => '2026-04-18 13:00:00']);
    // 5 dias atrás → dentro
    Application::factory()->for($user)->create(['applied_at' => '2026-04-19 13:00:00']);
    // 1 dia atrás → dentro (3ª candidatura → torna ativo)
    Application::factory()->for($user)->create(['applied_at' => '2026-04-23 13:00:00']);
    // 8 dias atrás → fora
    Application::factory()->for($user)->create(['applied_at' => '2026-04-16 11:00:00']);

    $this->actingAs($admin)
        ->getJson('/api/admin/metrics')
        ->assertJsonPath('totals.active_users', 1);
});

it('retorna ranking dos top 5 candidatos por número de candidaturas, em ordem desc', function (): void {
    $admin = User::factory()->admin()->create();

    /** @var array<int, User> $users */
    $users = [];
    foreach ([10, 8, 7, 5, 3, 2, 1] as $i => $count) {
        $u = User::factory()->create(['name' => "User {$i}"]);
        Application::factory()->for($u)->count($count)->create();
        $users[$i] = $u;
    }

    $response = $this->actingAs($admin)->getJson('/api/admin/metrics')->assertOk();

    $response->assertJsonPath('top_applicants.0.applications_count', 10);
    $response->assertJsonPath('top_applicants.1.applications_count', 8);
    $response->assertJsonPath('top_applicants.2.applications_count', 7);
    $response->assertJsonPath('top_applicants.3.applications_count', 5);
    $response->assertJsonPath('top_applicants.4.applications_count', 3);

    expect($response->json('top_applicants'))->toHaveCount(5);
});

it('ranking exclui usuários sem candidaturas', function (): void {
    $admin = User::factory()->admin()->create();

    $u1 = User::factory()->create();
    Application::factory()->for($u1)->count(2)->create();

    User::factory()->count(4)->create(); // todos sem candidaturas

    $response = $this->actingAs($admin)->getJson('/api/admin/metrics')->assertOk();

    expect($response->json('top_applicants'))->toHaveCount(1);
    $response->assertJsonPath('top_applicants.0.user_id', $u1->id);
});

it('agrega usuários por país, estado e cidade', function (): void {
    $admin = User::factory()->admin()->create();

    Profile::factory()->for(User::factory())->create([
        'country_code' => 'BR',
        'state_code' => 'SP',
        'state_name' => 'São Paulo',
        'city' => 'São Paulo',
    ]);
    Profile::factory()->for(User::factory())->create([
        'country_code' => 'BR',
        'state_code' => 'SP',
        'state_name' => 'São Paulo',
        'city' => 'Campinas',
    ]);
    Profile::factory()->for(User::factory())->create([
        'country_code' => 'US',
        'state_code' => 'CA',
        'state_name' => 'California',
        'city' => 'San Francisco',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/metrics')->assertOk();

    $countries = collect($response->json('by_location.countries'))->keyBy('country_code');
    expect($countries->get('BR')['count'])->toBe(2);
    expect($countries->get('US')['count'])->toBe(1);

    $states = $response->json('by_location.states');
    expect($states[0]['country_code'])->toBe('BR');
    expect($states[0]['state_code'])->toBe('SP');
    expect($states[0]['count'])->toBe(2);
});

it('conta usuários sem localização cadastrada', function (): void {
    $admin = User::factory()->admin()->create();

    Profile::factory()->for(User::factory())->create([
        'country_code' => 'BR',
        'state_name' => 'São Paulo',
        'city' => 'São Paulo',
    ]);

    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/admin/metrics')->assertOk();

    expect($response->json('by_location.without_location'))->toBe(4);
});

it('limita top estados a 10 e cidades a 15', function (): void {
    $admin = User::factory()->admin()->create();

    foreach (range(1, 20) as $i) {
        Profile::factory()->for(User::factory())->create([
            'country_code' => 'BR',
            'state_code' => "S{$i}",
            'state_name' => "State {$i}",
            'city' => "City {$i}",
        ]);
    }

    $response = $this->actingAs($admin)->getJson('/api/admin/metrics')->assertOk();

    expect($response->json('by_location.states'))->toHaveCount(10);
    expect($response->json('by_location.cities'))->toHaveCount(15);
});

it('endpoint /api/me devolve flag is_admin', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.is_admin', true);
});

it('endpoint /api/me devolve is_admin=false para usuário comum', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.is_admin', false);
});
