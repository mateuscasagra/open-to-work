<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;

it('shows a resume with its sections', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id, 'title' => 'Backend Sênior']);
    $resume->sections()->create([
        'type' => 'experience',
        'order' => 0,
        'content' => ['company' => 'Acme', 'role' => 'Senior Dev'],
    ]);

    $this->actingAs($user)
        ->getJson("/api/resumes/{$resume->id}")
        ->assertOk()
        ->assertJsonPath('title', 'Backend Sênior')
        ->assertJsonPath('sections.0.type', 'experience')
        ->assertJsonPath('sections.0.content.company', 'Acme');
});

it('returns 403 when viewing another user resume', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->getJson("/api/resumes/{$resume->id}")
        ->assertForbidden();
});
