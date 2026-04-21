<?php

declare(strict_types=1);

use App\Models\Job;
use App\Models\User;

it('shows job details with company and sources', function (): void {
    $user = User::factory()->create();
    $job = Job::factory()->create(['title' => 'Senior Go Engineer']);

    $this->actingAs($user)
        ->getJson("/api/jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('title', 'Senior Go Engineer')
        ->assertJsonStructure(['id', 'title', 'company', 'sources']);
});

it('returns 404 for non-existent job', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/jobs/99999')
        ->assertNotFound();
});
