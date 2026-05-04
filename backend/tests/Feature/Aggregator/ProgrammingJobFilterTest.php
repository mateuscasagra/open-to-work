<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Filters\ProgrammingJobFilter;

it('aceita vagas de programação pelo título', function (string $title): void {
    expect((new ProgrammingJobFilter)->isProgrammingJob($title))->toBeTrue();
})->with([
    'Senior Backend Engineer',
    'Junior Frontend Developer (React)',
    'Full Stack Developer',
    'DevOps Engineer',
    'Site Reliability Engineer',
    'PHP / Laravel Developer',
    'iOS Mobile Developer',
    'Data Engineer',
    'Machine Learning Engineer',
    'QA Automation Engineer',
    'Tech Lead',
    'Embedded Software Engineer',
    'Cloud Platform Engineer',
]);

it('aceita vagas pelas tags mesmo com título genérico', function (): void {
    expect((new ProgrammingJobFilter)->isProgrammingJob('Tech opportunity', ['python', 'django']))->toBeTrue();
});

it('rejeita vagas de áreas que não são programação', function (string $title): void {
    expect((new ProgrammingJobFilter)->isProgrammingJob($title))->toBeFalse();
})->with([
    'Junior Product Designer',
    'Senior Marketing Manager',
    'Account Executive',
    'Technical Recruiter',
    'Customer Success Specialist',
    'Sales Development Representative',
    'Copywriter',
    'UX Researcher',
    'HR Business Partner',
    'Mechanical Engineer',
]);

it('rejeita vaga sem título e sem tags', function (): void {
    expect((new ProgrammingJobFilter)->isProgrammingJob(''))->toBeFalse();
});

it('ignora tags vazias ou não-string', function (): void {
    /** @var iterable<int, mixed> $tags */
    $tags = ['', null, 'react'];

    expect((new ProgrammingJobFilter)->isProgrammingJob('Generic role', $tags))->toBeTrue();
});
