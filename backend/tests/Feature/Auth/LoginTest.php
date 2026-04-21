<?php

declare(strict_types=1);

use App\Models\User;

it('logs in with valid credentials', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Secret123!')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Secret123!',
    ]);

    $response->assertOk()->assertJsonPath('user.id', $user->id);
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Secret123!')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->assertGuest();
});

it('validates required fields', function (): void {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
