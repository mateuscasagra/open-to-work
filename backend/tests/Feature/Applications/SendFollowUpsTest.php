<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use App\Notifications\ApplicationFollowUpNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

it('notifies users whose applications are idle for 7+ days', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-04-18 10:00:00');

    $user = User::factory()->create();
    $stale = Application::factory()->create([
        'user_id' => $user->id,
        'status' => ApplicationStatus::Applied->value,
        'applied_at' => now()->subDays(10),
    ]);
    $fresh = Application::factory()->create([
        'user_id' => $user->id,
        'status' => ApplicationStatus::Applied->value,
        'applied_at' => now()->subDays(2),
    ]);

    $this->artisan('applications:send-followups')->assertSuccessful();

    Notification::assertSentTo($user, ApplicationFollowUpNotification::class, function ($notification) use ($stale): bool {
        return $notification->application->id === $stale->id;
    });
    Notification::assertCount(1);

    expect($stale->fresh()->events()->where('event_type', 'followup_sent')->exists())->toBeTrue();
    expect($fresh->fresh()->events()->where('event_type', 'followup_sent')->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('skips applications in terminal status', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-04-18 10:00:00');

    $user = User::factory()->create();
    foreach ([ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn] as $status) {
        Application::factory()->create([
            'user_id' => $user->id,
            'status' => $status->value,
            'applied_at' => now()->subDays(30),
        ]);
    }

    $this->artisan('applications:send-followups')->assertSuccessful();

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not resend when a followup event was logged recently', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-04-18 10:00:00');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => ApplicationStatus::Applied->value,
        'applied_at' => now()->subDays(20),
    ]);
    $application->events()->create([
        'event_type' => 'followup_sent',
        'payload' => ['days_since_applied' => 15],
        'occurred_at' => now()->subDays(3),
    ]);

    $this->artisan('applications:send-followups')->assertSuccessful();

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('skips applications with recent status changes', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-04-18 10:00:00');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => ApplicationStatus::Screening->value,
        'applied_at' => now()->subDays(20),
    ]);
    $application->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'applied', 'to' => 'screening'],
        'occurred_at' => now()->subDays(2),
    ]);

    $this->artisan('applications:send-followups')->assertSuccessful();

    Notification::assertNothingSent();

    Carbon::setTestNow();
});
