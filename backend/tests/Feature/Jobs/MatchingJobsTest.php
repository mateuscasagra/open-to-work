<?php

declare(strict_types=1);

use App\Enums\Modality;
use App\Enums\Seniority;
use App\Models\Job;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->create([
        'user_id' => $this->user->id,
        'modality' => Modality::Remote->value,
        'seniority' => Seniority::Senior->value,
        'languages' => ['pt_BR'],
    ]);

    $php = Skill::query()->create(['name' => 'php', 'category' => 'language', 'aliases' => []]);
    $laravel = Skill::query()->create(['name' => 'laravel', 'category' => 'framework', 'aliases' => []]);
    $this->profile->skills()->attach([$php->id, $laravel->id]);
});

it('returns only jobs that share at least one skill with the profile', function (): void {
    Job::factory()->create(['stack' => ['php', 'laravel'], 'title' => 'Match 1']);
    Job::factory()->create(['stack' => ['python', 'django'], 'title' => 'No match']);
    Job::factory()->create(['stack' => ['php'], 'title' => 'Match 2']);

    $response = $this->actingAs($this->user)->getJson('/api/jobs/matching');

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Match 1', 'Match 2')
        ->and($titles)->not->toContain('No match');
});

it('scores jobs by intersection size, modality, and seniority match', function (): void {
    $perfect = Job::factory()->create([
        'stack' => ['php', 'laravel'],
        'modality' => Modality::Remote->value,
        'seniority' => Seniority::Senior->value,
        'title' => 'Perfect',
    ]);
    $partial = Job::factory()->create([
        'stack' => ['php'],
        'modality' => Modality::Onsite->value,
        'seniority' => Seniority::Junior->value,
        'title' => 'Partial',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/jobs/matching');

    $response->assertOk();
    $data = $response->json('data');
    expect($data[0]['title'])->toBe('Perfect')
        ->and($data[0]['match_score'])->toBeGreaterThan($data[1]['match_score']);
});

it('rejects unauthenticated access to matching endpoint', function (): void {
    $this->getJson('/api/jobs/matching')->assertUnauthorized();
});

it('returns no matches when user has no profile skills', function (): void {
    $this->profile->skills()->detach();
    Job::factory()->create(['stack' => ['php', 'laravel']]);

    $response = $this->actingAs($this->user)->getJson('/api/jobs/matching');

    $response->assertOk()->assertJsonCount(0, 'data');
});
