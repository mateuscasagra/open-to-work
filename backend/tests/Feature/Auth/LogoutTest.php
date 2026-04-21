<?php

declare(strict_types=1);

use App\Models\User;

it('logs out an authenticated user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertGuest();
});

it('rejects logout when unauthenticated', function (): void {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});
