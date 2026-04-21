<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Domain\Job\Aggregator\Pipeline\PersistJob;
use App\Enums\Modality;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobSource;

function persistDto(): JobDTO
{
    return new JobDTO(
        source: 'remote_ok', externalId: '42', externalUrl: 'https://remoteok.com/42',
        title: 'Backend Dev', companyName: 'NewCo', companyLogoUrl: null,
        descriptionHtml: '<p>x</p>', location: 'Remote',
        modality: Modality::Remote, seniority: null, stack: ['php'],
        salaryMin: null, salaryMax: null, salaryCurrency: null,
        postedAt: null, expiresAt: null,
    );
}

it('creates a new job + company + source on first import', function (): void {
    $dto = persistDto();
    $stage = new PersistJob();

    $job = $stage->handle([$dto, null], fn ($j) => $j);

    expect($job)->toBeInstanceOf(Job::class)
        ->and(Company::where('name', 'NewCo')->exists())->toBeTrue()
        ->and(JobSource::where('job_id', $job->id)->where('source', 'remote_ok')->exists())->toBeTrue();
});

it('reuses existing job and only attaches new source', function (): void {
    $dto = persistDto();
    $company = Company::factory()->create(['name' => 'NewCo']);
    $existing = Job::factory()->create([
        'canonical_hash' => $dto->canonicalHash(),
        'company_id' => $company->id,
    ]);

    $stage = new PersistJob();
    $result = $stage->handle([$dto, $existing], fn ($j) => $j);

    expect($result->id)->toBe($existing->id);
    expect(Job::count())->toBe(1);
    expect(JobSource::where('job_id', $existing->id)->count())->toBe(1);
});

it('idempotently updates fetched_at on repeated imports', function (): void {
    $dto = persistDto();
    $stage = new PersistJob();

    $job1 = $stage->handle([$dto, null], fn ($j) => $j);
    $firstFetch = JobSource::where('job_id', $job1->id)->first()->fetched_at;

    sleep(1);

    $job2 = $stage->handle([$dto, $job1], fn ($j) => $j);
    $secondFetch = JobSource::where('job_id', $job2->id)->first()->fetched_at;

    expect($secondFetch)->not->toEqual($firstFetch);
    expect(JobSource::count())->toBe(1);
});
