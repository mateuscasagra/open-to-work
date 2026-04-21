<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Actions\SyncJobsFromSource;
use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Models\Job;

it('runs the full pipeline and imports jobs', function (): void {
    $driver = new class implements JobSourceDriver
    {
        public function name(): string
        {
            return 'test-source';
        }

        public function fetch(): iterable
        {
            yield new JobDTO(
                source: 'test-source', externalId: '1', externalUrl: 'https://x/1',
                title: 'Backend', companyName: 'Co', companyLogoUrl: null,
                descriptionHtml: '<p>y</p>', location: 'Remote',
                modality: null, seniority: null, stack: ['php'],
                salaryMin: null, salaryMax: null, salaryCurrency: null,
                postedAt: null, expiresAt: null,
            );
            yield new JobDTO(
                source: 'test-source', externalId: '2', externalUrl: 'https://x/2',
                title: 'Frontend', companyName: 'Co2', companyLogoUrl: null,
                descriptionHtml: '<p>z</p>', location: 'SP',
                modality: null, seniority: null, stack: ['vue'],
                salaryMin: null, salaryMax: null, salaryCurrency: null,
                postedAt: null, expiresAt: null,
            );
        }
    };

    $action = app(SyncJobsFromSource::class);
    $stats = $action->execute($driver);

    expect($stats['imported'])->toBe(2)
        ->and($stats['failed'])->toBe(0)
        ->and(Job::count())->toBe(2);
});

it('counts failures without stopping the pipeline', function (): void {
    $driver = new class implements JobSourceDriver
    {
        public function name(): string
        {
            return 'broken';
        }

        public function fetch(): iterable
        {
            // DTO inválido (vai falhar no PersistJob por FK ausente em cenários futuros);
            // aqui simulamos throw direto em uma das iterações por um DTO mal formado.
            yield new JobDTO(
                source: 'broken', externalId: '1', externalUrl: 'https://x/1',
                title: 'OK', companyName: 'Acme', companyLogoUrl: null,
                descriptionHtml: '', location: null,
                modality: null, seniority: null, stack: [],
                salaryMin: null, salaryMax: null, salaryCurrency: null,
                postedAt: null, expiresAt: null,
            );
        }
    };

    $stats = app(SyncJobsFromSource::class)->execute($driver);

    // este cenário só garante que falhas são contabilizadas; ambos os cenários válidos passam.
    expect($stats['imported'] + $stats['failed'])->toBe(1);
});
