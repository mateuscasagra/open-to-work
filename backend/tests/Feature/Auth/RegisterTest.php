<?php

declare(strict_types=1);

use App\Models\User;

it('registers a user and logs them in', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Diego',
        'email' => 'diego@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.email', 'diego@example.com');

    expect(User::where('email', 'diego@example.com')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

it('requires valid email and password', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'X',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('blocks duplicate emails', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New',
        'email' => 'taken@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});
