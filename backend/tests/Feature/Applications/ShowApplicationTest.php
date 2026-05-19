<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;

it('shows my application with relations', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'job_url' => 'https://example.com/vaga/1',
    ]);

    $this->actingAs($user)
        ->getJson("/api/applications/{$application->id}")
        ->assertOk()
        ->assertJsonPath('id', $application->id)
        ->assertJsonPath('job_url', 'https://example.com/vaga/1')
        ->assertJsonStructure(['id', 'status', 'job', 'events', 'job_url']);
});

it('blocks access to another user application', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherApp = Application::factory()->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->getJson("/api/applications/{$otherApp->id}")
        ->assertForbidden();
});
