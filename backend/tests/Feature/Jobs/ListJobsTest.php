<?php

declare(strict_types=1);

use App\Enums\Modality;
use App\Enums\Seniority;
use App\Models\Job;
use App\Models\User;

it('lists active jobs with pagination', function (): void {
    $user = User::factory()->create();
    Job::factory()->count(25)->create(['active' => true]);
    Job::factory()->count(3)->create(['active' => false]);

    $response = $this->actingAs($user)->getJson('/api/jobs');

    $response->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page'])
        ->assertJsonCount(20, 'data');
});

it('filters by modality', function (): void {
    $user = User::factory()->create();
    Job::factory()->count(3)->create(['modality' => Modality::Remote->value]);
    Job::factory()->count(2)->create(['modality' => Modality::Onsite->value]);

    $this->actingAs($user)
        ->getJson('/api/jobs?modality=remote')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters by seniority', function (): void {
    $user = User::factory()->create();
    Job::factory()->count(2)->create(['seniority' => Seniority::Senior->value]);
    Job::factory()->count(4)->create(['seniority' => Seniority::Junior->value]);

    $this->actingAs($user)
        ->getJson('/api/jobs?seniority=senior')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by stack tag', function (): void {
    $user = User::factory()->create();
    Job::factory()->create(['stack' => ['php', 'laravel']]);
    Job::factory()->create(['stack' => ['python', 'django']]);
    Job::factory()->create(['stack' => ['php', 'symfony']]);

    $response = $this->actingAs($user)->getJson('/api/jobs?stack=php');

    $response->assertOk();
    $this->assertGreaterThanOrEqual(2, count($response->json('data')));
});

it('rejects unauthenticated access', function (): void {
    $this->getJson('/api/jobs')->assertUnauthorized();
});

it('includes sources with external_url for apply link', function (): void {
    $user = User::factory()->create();
    $job = Job::factory()->create();
    $job->sources()->create([
        'source' => 'remoteok',
        'external_id' => 'abc-123',
        'external_url' => 'https://remoteok.com/jobs/abc-123',
        'fetched_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/jobs');

    $response->assertOk()
        ->assertJsonPath('data.0.sources.0.external_url', 'https://remoteok.com/jobs/abc-123');
});
