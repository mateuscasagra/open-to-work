<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('creates an application and dispatches ApplicationCreated', function (): void {
    Event::fake([ApplicationCreated::class]);

    $user = User::factory()->create();
    $job = Job::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/applications', [
        'jobId' => $job->id,
        'source' => 'linkedin',
        'jobUrl' => 'https://linkedin.com/jobs/123',
        'notes' => 'Match perfeito com minha stack',
        'expectedSalary' => 15000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', ApplicationStatus::Applied->value)
        ->assertJsonPath('source', 'linkedin')
        ->assertJsonPath('job_url', 'https://linkedin.com/jobs/123');

    expect(Application::count())->toBe(1);
    expect(Application::first()->job_url)->toBe('https://linkedin.com/jobs/123');
    Event::assertDispatched(ApplicationCreated::class);
});

it('rejects duplicate application for same job with 409', function (): void {
    $user = User::factory()->create();
    $job = Job::factory()->create();
    Application::factory()->create(['user_id' => $user->id, 'job_id' => $job->id]);

    $this->actingAs($user)
        ->postJson('/api/applications', ['jobId' => $job->id])
        ->assertStatus(409)
        ->assertJsonPath('message', 'You have already applied to this job.');

    expect(Application::count())->toBe(1);
});

it('validates required fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/applications', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['manualTitle']);
});

it('rejects non-existent job', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/applications', ['jobId' => 99999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['jobId']);
});
