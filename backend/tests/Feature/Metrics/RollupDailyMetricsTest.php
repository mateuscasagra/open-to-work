<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\MetricsDaily;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

it('materializes applications counts and channels per user per date', function (): void {
    Carbon::setTestNow('2026-04-18 12:00:00');
    $user = User::factory()->create();

    $date = CarbonImmutable::parse('2026-04-17');

    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 09:00:00',
        'source' => 'linkedin',
    ]);
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 15:00:00',
        'source' => 'linkedin',
    ]);
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 18:30:00',
        'source' => 'gupy',
    ]);
    // fora do intervalo
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-16 10:00:00',
        'source' => 'linkedin',
    ]);

    $this->artisan('metrics:rollup-daily', ['--date' => '2026-04-17'])->assertSuccessful();

    $row = MetricsDaily::query()->where('user_id', $user->id)->where('date', '2026-04-17')->first();

    expect($row)->not->toBeNull();
    expect($row->applications_count)->toBe(3);
    expect($row->breakdown['channels']['linkedin'])->toBe(2);
    expect($row->breakdown['channels']['gupy'])->toBe(1);

    Carbon::setTestNow();
});

it('counts responses, interviews, offers and rejections from status_changed events', function (): void {
    Carbon::setTestNow('2026-04-18 12:00:00');
    $user = User::factory()->create();

    $app = Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 09:00:00',
        'status' => ApplicationStatus::InterviewHR->value,
    ]);

    $app->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => ApplicationStatus::Applied->value, 'to' => ApplicationStatus::Screening->value],
        'occurred_at' => '2026-04-17 11:00:00',
    ]);
    $app->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => ApplicationStatus::Screening->value, 'to' => ApplicationStatus::InterviewHR->value],
        'occurred_at' => '2026-04-17 14:00:00',
    ]);

    $rejected = Application::factory()->for($user)->create([
        'applied_at' => '2026-04-16 09:00:00',
        'status' => ApplicationStatus::Rejected->value,
    ]);
    $rejected->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => ApplicationStatus::Applied->value, 'to' => ApplicationStatus::Rejected->value],
        'occurred_at' => '2026-04-17 08:00:00',
    ]);

    $this->artisan('metrics:rollup-daily', ['--date' => '2026-04-17'])->assertSuccessful();

    $row = MetricsDaily::query()->where('user_id', $user->id)->where('date', '2026-04-17')->first();

    expect($row->responses_count)->toBe(1); // applied → screening
    expect($row->interviews_count)->toBe(1); // screening → interview_hr
    expect($row->rejections_count)->toBe(1); // applied → rejected
    expect($row->offers_count)->toBe(0);

    Carbon::setTestNow();
});

it('is idempotent when rerun for the same date', function (): void {
    $user = User::factory()->create();
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 09:00:00',
        'source' => 'linkedin',
    ]);

    $this->artisan('metrics:rollup-daily', ['--date' => '2026-04-17'])->assertSuccessful();
    $this->artisan('metrics:rollup-daily', ['--date' => '2026-04-17'])->assertSuccessful();

    expect(MetricsDaily::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('defaults to yesterday when no date is provided', function (): void {
    Carbon::setTestNow('2026-04-18 12:00:00');
    $user = User::factory()->create();
    Application::factory()->for($user)->create(['applied_at' => '2026-04-17 10:00:00', 'source' => 'linkedin']);

    $this->artisan('metrics:rollup-daily')->assertSuccessful();

    $row = MetricsDaily::query()->where('user_id', $user->id)->first();
    expect($row->date)->toBe('2026-04-17');

    Carbon::setTestNow();
});
