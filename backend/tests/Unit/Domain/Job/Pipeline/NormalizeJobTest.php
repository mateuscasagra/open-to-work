<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Domain\Job\Aggregator\Pipeline\NormalizeJob;

it('trims and collapses whitespace in title', function (): void {
    $dto = makeDto(title: '  Senior    PHP   Dev  ');
    $stage = new NormalizeJob;

    $result = $stage->handle($dto, fn ($out) => $out);

    expect($result->title)->toBe('Senior PHP Dev');
});

it('lowercases and deduplicates stack tags', function (): void {
    $dto = makeDto(stack: ['PHP', 'php', 'Laravel', 'LARAVEL', '  vue  ']);
    $result = (new NormalizeJob)->handle($dto, fn ($out) => $out);

    expect($result->stack)->toBe(['php', 'laravel', 'vue']);
});

it('strips dangerous html from description', function (): void {
    $dto = makeDto(description: '<script>alert(1)</script><p>safe</p>');
    $result = (new NormalizeJob)->handle($dto, fn ($out) => $out);

    expect($result->descriptionHtml)->not->toContain('<script>');
    expect($result->descriptionHtml)->toContain('<p>safe</p>');
});

function makeDto(
    string $title = 'Title',
    array $stack = [],
    string $description = '',
): JobDTO {
    return new JobDTO(
        source: 'test',
        externalId: '1',
        externalUrl: 'https://example.com',
        title: $title,
        companyName: 'Acme',
        companyLogoUrl: null,
        descriptionHtml: $description,
        location: 'Remote',
        modality: null,
        seniority: null,
        stack: $stack,
        salaryMin: null,
        salaryMax: null,
        salaryCurrency: null,
        postedAt: null,
        expiresAt: null,
    );
}
