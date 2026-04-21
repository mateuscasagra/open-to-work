<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;

it('deletes my application', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/applications/{$app->id}")
        ->assertNoContent();

    expect(Application::find($app->id))->toBeNull();
});

it('blocks deleting another user application', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->deleteJson("/api/applications/{$app->id}")
        ->assertForbidden();

    expect(Application::find($app->id))->not->toBeNull();
});
