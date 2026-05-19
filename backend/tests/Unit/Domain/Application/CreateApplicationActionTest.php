<?php

declare(strict_types=1);

use App\Domain\Application\Actions\CreateApplication;
use App\Domain\Application\DTOs\ApplicationData;
use App\Enums\ApplicationStatus;
use App\Events\ApplicationCreated;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('creates application with Applied status', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $job = Job::factory()->create();

    $application = app(CreateApplication::class)->execute(
        $user,
        new ApplicationData(
            jobId: $job->id,
            jobUrl: 'https://linkedin.com/jobs/9876',
            source: 'linkedin',
        )
    );

    expect($application->status)->toBe(ApplicationStatus::Applied)
        ->and($application->user_id)->toBe($user->id)
        ->and($application->job_id)->toBe($job->id)
        ->and($application->job_url)->toBe('https://linkedin.com/jobs/9876');

    Event::assertDispatched(ApplicationCreated::class);
});
