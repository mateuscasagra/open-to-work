<?php

declare(strict_types=1);

use App\Models\User;

it('returns the authenticated user with subscription envelope', function (): void {
    $user = User::factory()->create(['name' => 'Diego']);

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.name', 'Diego')
        ->assertJsonPath('subscription.plan', 'free')
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.quota.limit', 15)
        ->assertJsonPath('subscription.quota.used', 0)
        ->assertJsonPath('subscription.quota.plan', 'free')
        ->assertJsonPath('subscription.pro_price_cents', 2500)  // seeded em plans table
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'subscription' => [
                'plan', 'status', 'current_period_end', 'canceled_at', 'pro_price_cents',
                'quota' => ['used', 'limit', 'reset_at', 'plan'],
            ],
        ]);
});

it('reflects pro_price_cents from plans table (not env)', function (): void {
    $user = User::factory()->create();

    // Atualiza preço direto no DB — simula admin mudando valor via psql.
    // Query builder bypassa eventos Eloquent → cache não é invalidado pelo hook,
    // por isso fazemos forget manual (em prod o admin esperaria 60s ou rodaria
    // `php artisan cache:forget plans.pro.price_cents`).
    \App\Models\Plan::where('slug', 'pro')->update(['price_cents' => 4990]);
    \Illuminate\Support\Facades\Cache::forget('plans.pro.price_cents');

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('subscription.pro_price_cents', 4990);
});

it('exposes pricing publicly via /api/pricing for landing', function (): void {
    $this->getJson('/api/pricing')
        ->assertOk()
        ->assertJsonPath('free.price_cents', 0)
        ->assertJsonPath('pro.price_cents', 2500)
        ->assertJsonStructure([
            'free' => ['price_cents'],
            'pro' => ['price_cents'],
        ]);
});

it('rejects unauthenticated me call', function (): void {
    $this->getJson('/api/me')->assertUnauthorized();
});
