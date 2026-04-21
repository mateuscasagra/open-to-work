<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Models\Job;

beforeEach(function (): void {
    app()->bind('job.driver.fake_source', function () {
        return new class implements JobSourceDriver
        {
            public function name(): string
            {
                return 'fake_source';
            }

            public function fetch(): iterable
            {
                yield new JobDTO(
                    source: 'fake_source', externalId: '1', externalUrl: 'https://x/1',
                    title: 'Cmd Dev', companyName: 'CmdCo', companyLogoUrl: null,
                    descriptionHtml: '', location: null,
                    modality: null, seniority: null, stack: [],
                    salaryMin: null, salaryMax: null, salaryCurrency: null,
                    postedAt: null, expiresAt: null,
                );
            }
        };
    });
});

it('aggregates explicit source when passed as argument', function (): void {
    $this->artisan('jobs:aggregate', ['source' => ['fake_source']])
        ->expectsOutputToContain('fake_source')
        ->expectsOutputToContain('imported=1')
        ->assertExitCode(0);

    expect(Job::where('title', 'Cmd Dev')->exists())->toBeTrue();
});

it('warns when no sources are configured', function (): void {
    config()->set('aggregator.enabled_sources', []);

    $this->artisan('jobs:aggregate')
        ->expectsOutputToContain('Nenhuma fonte habilitada')
        ->assertExitCode(0);
});

it('skips unknown drivers gracefully', function (): void {
    $this->artisan('jobs:aggregate', ['source' => ['unknown_source']])
        ->expectsOutputToContain('Driver não encontrado')
        ->assertExitCode(0);
});
