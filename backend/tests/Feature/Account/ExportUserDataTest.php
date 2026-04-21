<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Job;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;

it('returns a JSON download with the full user bundle', function (): void {
    $user = User::factory()->create(['name' => 'Diego', 'email' => 'diego@example.com']);
    $skill = Skill::factory()->create(['name' => 'PHP']);
    $profile = $user->profile()->create([
        'desired_role' => 'Backend Senior',
        'languages' => ['pt_BR', 'en'],
    ]);
    $profile->skills()->attach($skill->id, ['proficiency' => 5]);

    $resume = Resume::factory()->create(['user_id' => $user->id, 'title' => 'Backend']);
    $job = Job::factory()->create(['title' => 'PHP Dev']);
    $application = Application::factory()->for($user)->for($job)->create(['source' => 'linkedin']);
    $application->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'applied', 'to' => 'screening'],
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/api/account/export');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json');
    $content = $response->streamedContent();
    $payload = json_decode($content, true);

    expect($payload['user']['email'])->toBe('diego@example.com');
    expect($payload['profile']['desired_role'])->toBe('Backend Senior');
    expect($payload['profile']['skills'][0]['name'])->toBe('PHP');
    expect($payload['resumes'])->toHaveCount(1);
    expect($payload['resumes'][0]['title'])->toBe('Backend');
    expect($payload['applications'])->toHaveCount(1);
    expect($payload['applications'][0]['job']['title'])->toBe('PHP Dev');
    expect($payload['applications'][0]['events'][0]['event_type'])->toBe('status_changed');
});

it('requires authentication', function (): void {
    $this->getJson('/api/account/export')->assertUnauthorized();
});

it('does not leak other users data', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherApp = Application::factory()->for($other)->create();

    $response = $this->actingAs($user)->get('/api/account/export');

    $payload = json_decode($response->streamedContent(), true);
    expect($payload['applications'])->toBeEmpty();
    expect(data_get($payload, 'user.id'))->toBe($user->id);
    unset($otherApp);
});
