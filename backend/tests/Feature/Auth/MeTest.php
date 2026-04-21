<?php

declare(strict_types=1);

use App\Models\User;

it('returns the authenticated user', function (): void {
    $user = User::factory()->create(['name' => 'Diego']);

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.name', 'Diego');
});

it('rejects unauthenticated me call', function (): void {
    $this->getJson('/api/me')->assertUnauthorized();
});
