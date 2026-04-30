<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;

it('archives an application and records timeline event', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $user->id, 'archived_at' => null]);

    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/archive")
        ->assertOk()
        ->assertJsonPath('archived_at', fn ($v) => $v !== null);

    expect($app->fresh()->archived_at)->not->toBeNull();
    expect($app->events()->where('event_type', 'archived')->count())->toBe(1);
});

it('unarchives an application and records timeline event', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $user->id, 'archived_at' => now()]);

    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/unarchive")
        ->assertOk()
        ->assertJsonPath('archived_at', null);

    expect($app->fresh()->archived_at)->toBeNull();
    expect($app->events()->where('event_type', 'unarchived')->count())->toBe(1);
});

it('archive is idempotent — does not duplicate event when already archived', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $user->id, 'archived_at' => now()]);

    $this->actingAs($user)
        ->postJson("/api/applications/{$app->id}/archive")
        ->assertOk();

    expect($app->events()->where('event_type', 'archived')->count())->toBe(0);
});

it('forbids archiving another user application', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->postJson("/api/applications/{$app->id}/archive")
        ->assertForbidden();
});

it('index hides archived applications by default', function (): void {
    $user = User::factory()->create();
    Application::factory()->count(2)->create(['user_id' => $user->id, 'archived_at' => null]);
    Application::factory()->count(3)->create(['user_id' => $user->id, 'archived_at' => now()]);

    $this->actingAs($user)
        ->getJson('/api/applications')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('index returns only archived when archived=1', function (): void {
    $user = User::factory()->create();
    Application::factory()->count(2)->create(['user_id' => $user->id, 'archived_at' => null]);
    Application::factory()->count(3)->create(['user_id' => $user->id, 'archived_at' => now()]);

    $this->actingAs($user)
        ->getJson('/api/applications?archived=1')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
