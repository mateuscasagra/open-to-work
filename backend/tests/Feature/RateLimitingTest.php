<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    RateLimiter::clear('auth');
});

it('throttles repeated failed logins by IP', function (): void {
    // 10 por minuto por IP + 5 por e-mail; ao 11º pelo mesmo IP com e-mails diferentes cai em 429.
    for ($i = 0; $i < 10; $i++) {
        $response = $this->postJson('/api/auth/login', [
            'email' => "user{$i}@example.com",
            'password' => 'wrong-password',
        ]);
        expect($response->status())->not->toBe(429);
    }

    $this->postJson('/api/auth/login', [
        'email' => 'user99@example.com',
        'password' => 'wrong',
    ])->assertStatus(429);
});

it('throttles register route', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/auth/register', [
            'name' => "User {$i}",
            'email' => "u{$i}@example.com",
            'password' => 'Abc12345',
            'password_confirmation' => 'Abc12345',
        ]);
    }

    $this->postJson('/api/auth/register', [
        'name' => 'Over Limit',
        'email' => 'over@example.com',
        'password' => 'Abc12345',
        'password_confirmation' => 'Abc12345',
    ])->assertStatus(429);
});
