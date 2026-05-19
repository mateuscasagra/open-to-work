<?php

declare(strict_types=1);

use App\Domain\Application\Actions\CreateApplication;
use App\Domain\Application\DTOs\ApplicationData;
use App\Enums\ApplicationStatus;
use App\Events\ApplicationCreated;
use App\Models\Job;
use App\Models\JobSource;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('creates application with Applied status and auto-fills url/description from job when not provided', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $job = Job::factory()->create([
        'description_html' => '<p>Backend Engineer position.</p><p>Stack: PHP, Laravel.</p>',
    ]);
    JobSource::create([
        'job_id' => $job->id,
        'source' => 'linkedin',
        'external_url' => 'https://linkedin.com/jobs/9876',
    ]);

    $application = app(CreateApplication::class)->execute(
        $user,
        new ApplicationData(jobId: $job->id, source: 'linkedin')
    );

    expect($application->status)->toBe(ApplicationStatus::Applied)
        ->and($application->user_id)->toBe($user->id)
        ->and($application->job_id)->toBe($job->id)
        ->and($application->job_url)->toBe('https://linkedin.com/jobs/9876')
        ->and($application->notes)->toContain('Backend Engineer position')
        ->and($application->notes)->toContain('Stack: PHP, Laravel');

    Event::assertDispatched(ApplicationCreated::class);
});
