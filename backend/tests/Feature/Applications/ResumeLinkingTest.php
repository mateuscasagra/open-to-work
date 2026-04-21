<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Job;
use App\Models\Resume;
use App\Models\User;

it('creates application linked to own resume', function (): void {
    $user = User::factory()->create();
    $job = Job::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/applications', [
            'jobId' => $job->id,
            'resumeId' => $resume->id,
        ])
        ->assertCreated()
        ->assertJsonPath('resume_id', $resume->id);

    expect(Application::first()->resume_id)->toBe($resume->id);
});

it('rejects application using another user resume', function (): void {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    $job = Job::factory()->create();
    $strangerResume = Resume::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($me)
        ->postJson('/api/applications', [
            'jobId' => $job->id,
            'resumeId' => $strangerResume->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['resumeId']);

    expect(Application::count())->toBe(0);
});

it('updates application resume_id to own resume', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $user->id, 'resume_id' => null]);
    $resume = Resume::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/api/applications/{$application->id}", ['resume_id' => $resume->id])
        ->assertOk()
        ->assertJsonPath('resume_id', $resume->id);

    expect($application->fresh()->resume_id)->toBe($resume->id);
});

it('rejects updating application with another user resume', function (): void {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $me->id]);
    $strangerResume = Resume::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($me)
        ->putJson("/api/applications/{$application->id}", ['resume_id' => $strangerResume->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['resume_id']);
});

it('clears resume link when resume_id is null', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'resume_id' => $resume->id,
    ]);

    $this->actingAs($user)
        ->putJson("/api/applications/{$application->id}", ['resume_id' => null])
        ->assertOk()
        ->assertJsonPath('resume_id', null);

    expect($application->fresh()->resume_id)->toBeNull();
});
