<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;

it('lists only the authenticated user resumes', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Resume::factory()->count(2)->create(['user_id' => $user->id]);
    Resume::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->getJson('/api/resumes')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires authentication', function (): void {
    $this->getJson('/api/resumes')->assertUnauthorized();
});
