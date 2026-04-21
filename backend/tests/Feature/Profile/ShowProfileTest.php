<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;

it('creates profile on first fetch if missing', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('user_id', $user->id);

    expect(Profile::where('user_id', $user->id)->exists())->toBeTrue();
});

it('returns existing profile with skills', function (): void {
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id, 'desired_role' => 'Senior Dev']);

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('desired_role', 'Senior Dev')
        ->assertJsonStructure(['id', 'desired_role', 'seniority', 'modality', 'skills']);
});

it('rejects unauthenticated access', function (): void {
    $this->getJson('/api/profile')->assertUnauthorized();
});
