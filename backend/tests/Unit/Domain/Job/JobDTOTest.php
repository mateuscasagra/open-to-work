<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\DTOs\JobDTO;

it('computes a stable canonical hash', function (): void {
    $dto1 = makeJob(title: 'Senior Backend Engineer', company: 'Acme Inc', location: 'Remote');
    $dto2 = makeJob(title: '  senior backend engineer  ', company: 'ACME INC', location: 'REMOTE');

    expect($dto1->canonicalHash())->toBe($dto2->canonicalHash());
});

it('produces different hash for different jobs', function (): void {
    $a = makeJob(title: 'Frontend Dev', company: 'X', location: 'SP');
    $b = makeJob(title: 'Backend Dev', company: 'X', location: 'SP');

    expect($a->canonicalHash())->not->toBe($b->canonicalHash());
});

function makeJob(string $title, string $company, string $location): JobDTO
{
    return new JobDTO(
        source: 'test',
        externalId: '1',
        externalUrl: 'https://example.com/1',
        title: $title,
        companyName: $company,
        companyLogoUrl: null,
        descriptionHtml: '',
        location: $location,
        modality: null,
        seniority: null,
        stack: [],
        salaryMin: null,
        salaryMax: null,
        salaryCurrency: null,
        postedAt: null,
        expiresAt: null,
    );
}
