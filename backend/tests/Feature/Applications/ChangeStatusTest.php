<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Events\ApplicationStatusChanged;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('advances status through allowed transition', function (): void {
    Event::fake([ApplicationStatusChanged::class]);

    $user = User::factory()->create();
    $app = Application::factory()->inStatus(ApplicationStatus::Applied)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson("/api/applications/{$app->id}/status", [
            'status' => 'screening',
            'note' => 'Recrutador entrou em contato',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'screening');

    expect(ApplicationEvent::where('application_id', $app->id)->where('event_type', 'status_changed')->exists())->toBeTrue();
    Event::assertDispatched(ApplicationStatusChanged::class);
});

it('allows arbitrary transitions including skipping stages', function (): void {
    Event::fake([ApplicationStatusChanged::class]);

    $user = User::factory()->create();
    $app = Application::factory()->inStatus(ApplicationStatus::Applied)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson("/api/applications/{$app->id}/status", ['status' => 'offer'])
        ->assertOk()
        ->assertJsonPath('status', 'offer');

    expect($app->fresh()->status)->toBe(ApplicationStatus::Offer);
    Event::assertDispatched(ApplicationStatusChanged::class);
});

it('is a no-op when target equals current status', function (): void {
    Event::fake([ApplicationStatusChanged::class]);

    $user = User::factory()->create();
    $app = Application::factory()->inStatus(ApplicationStatus::Screening)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson("/api/applications/{$app->id}/status", ['status' => 'screening'])
        ->assertOk();

    expect(ApplicationEvent::where('application_id', $app->id)->where('event_type', 'status_changed')->count())->toBe(0);
    Event::assertNotDispatched(ApplicationStatusChanged::class);
});

it('rejects unknown status value', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson("/api/applications/{$app->id}/status", ['status' => 'unicorn'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('blocks changing another user application', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherApp = Application::factory()->inStatus(ApplicationStatus::Applied)->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->patchJson("/api/applications/{$otherApp->id}/status", ['status' => 'screening'])
        ->assertForbidden();
});
