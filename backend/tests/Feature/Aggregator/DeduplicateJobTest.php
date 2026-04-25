<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Domain\Job\Aggregator\Pipeline\DeduplicateJob;
use App\Models\Company;
use App\Models\Job;

function makeDto2(string $title, string $company, string $location): JobDTO
{
    return new JobDTO(
        source: 'test', externalId: '1', externalUrl: 'https://x.com',
        title: $title, companyName: $company, companyLogoUrl: null,
        descriptionHtml: '', location: $location,
        modality: null, seniority: null, stack: [],
        salaryMin: null, salaryMax: null, salaryCurrency: null,
        postedAt: null, expiresAt: null,
    );
}

it('passes null when job does not exist', function (): void {
    $dto = makeDto2('Go Dev', 'Acme', 'Remote');

    [$passedDto, $existing] = (new DeduplicateJob)->handle($dto, fn ($out) => $out);

    expect($passedDto)->toBe($dto);
    expect($existing)->toBeNull();
});

it('finds existing job with matching canonical hash', function (): void {
    $dto = makeDto2('Go Dev', 'Acme', 'Remote');
    $company = Company::factory()->create(['name' => 'Acme']);
    $existing = Job::factory()->create([
        'canonical_hash' => $dto->canonicalHash(),
        'company_id' => $company->id,
    ]);

    [, $found] = (new DeduplicateJob)->handle($dto, fn ($out) => $out);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($existing->id);
});
