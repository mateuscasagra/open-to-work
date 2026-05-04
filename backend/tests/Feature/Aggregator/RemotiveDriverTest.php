<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\RemotiveDriver;
use App\Enums\Modality;
use Illuminate\Support\Facades\Http;

it('fetches and transforms Remotive payload into JobDTOs', function (): void {
    Http::fake([
        'remotive.com/api/remote-jobs*' => Http::response([
            'jobs' => [
                [
                    'id' => 1357,
                    'url' => 'https://remotive.com/remote-jobs/software-dev/senior-backend-engineer-1357',
                    'title' => 'Senior Backend Engineer',
                    'company_name' => 'TechCorp',
                    'company_logo' => 'https://remotive.com/logos/techcorp.png',
                    'category' => 'Software Development',
                    'job_type' => 'full_time',
                    'candidate_required_location' => 'Worldwide',
                    'salary' => 'USD 100k - 150k',
                    'description' => '<p>Build backend systems</p>',
                    'publication_date' => '2026-04-10T08:30:00',
                    'tags' => ['python', 'django', 'postgresql'],
                ],
                [
                    'id' => 1359,
                    'url' => 'https://remotive.com/remote-jobs/software-dev/junior-frontend-1359',
                    'title' => 'Junior Frontend Developer',
                    'company_name' => 'WebShop',
                    'company_logo' => null,
                    'category' => 'Software Development',
                    'job_type' => 'contract',
                    'candidate_required_location' => 'Europe',
                    'salary' => '',
                    'description' => '<p>Build UIs in React</p>',
                    'publication_date' => '2026-04-09T12:00:00',
                    'tags' => ['react', 'typescript'],
                ],
            ],
        ]),
    ]);

    $results = iterator_to_array((new RemotiveDriver)->fetch());

    expect($results)->toHaveCount(2);

    [$a, $b] = $results;

    expect($a->source)->toBe('remotive')
        ->and($a->externalId)->toBe('1357')
        ->and($a->title)->toBe('Senior Backend Engineer')
        ->and($a->companyName)->toBe('TechCorp')
        ->and($a->modality)->toBe(Modality::Remote)
        ->and($a->seniority)->toBeNull()
        ->and($a->stack)->toBe(['python', 'django', 'postgresql'])
        ->and($a->salaryMin)->toBe(100000)
        ->and($a->salaryMax)->toBe(150000)
        ->and($a->salaryCurrency)->toBe('USD');

    expect($b->seniority)->toBeNull()
        ->and($b->salaryMin)->toBeNull()
        ->and($b->salaryMax)->toBeNull();
});

it('envia category=software-dev na request', function (): void {
    Http::fake([
        'remotive.com/api/remote-jobs*' => Http::response(['jobs' => []]),
    ]);

    iterator_to_array((new RemotiveDriver)->fetch());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'category=software-dev'));
});

it('descarta vagas que escaparem fora da categoria software-dev', function (): void {
    Http::fake([
        'remotive.com/api/remote-jobs*' => Http::response([
            'jobs' => [
                [
                    'id' => 1,
                    'url' => 'u',
                    'title' => 'Backend Engineer',
                    'company_name' => 'TechCo',
                    'tags' => ['go'],
                    'description' => '<p>Build APIs</p>',
                    'publication_date' => '2026-04-10T00:00:00',
                ],
                [
                    'id' => 2,
                    'url' => 'u',
                    'title' => 'Junior Product Designer',
                    'company_name' => 'DesignCo',
                    'tags' => ['figma'],
                    'description' => '<p>Design UIs</p>',
                    'publication_date' => '2026-04-09T00:00:00',
                ],
            ],
        ]),
    ]);

    $results = iterator_to_array((new RemotiveDriver)->fetch());

    expect($results)->toHaveCount(1)
        ->and($results[0]->title)->toBe('Backend Engineer');
});

it('skips items without id', function (): void {
    Http::fake([
        'remotive.com/api/remote-jobs*' => Http::response([
            'jobs' => [
                ['title' => 'No id'],
                ['id' => 1, 'url' => 'u', 'title' => 'Backend Developer', 'company_name' => 'X', 'tags' => ['python']],
            ],
        ]),
    ]);

    $results = iterator_to_array((new RemotiveDriver)->fetch());

    expect($results)->toHaveCount(1);
});
