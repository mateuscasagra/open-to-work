<?php

declare(strict_types=1);

use App\Models\User;

it('sets locale from Accept-Language header when unauthenticated', function (): void {
    $this->withHeaders(['Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8'])
        ->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'x'])
        ->assertUnprocessable();

    expect(app()->getLocale())->toBe('pt_BR');
});

it('falls back to default when Accept-Language is unsupported', function (): void {
    $this->withHeaders(['Accept-Language' => 'fr-FR'])
        ->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'x'])
        ->assertUnprocessable();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

it('prefers authenticated user locale over Accept-Language', function (): void {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'pt-BR'])
        ->getJson('/api/me')
        ->assertOk();

    expect(app()->getLocale())->toBe('es');
});
