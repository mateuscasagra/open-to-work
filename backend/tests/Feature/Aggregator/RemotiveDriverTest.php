<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\RemotiveDriver;
use App\Enums\Modality;
use App\Enums\Seniority;
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
                    'id' => 1358,
                    'url' => 'https://remotive.com/remote-jobs/design/junior-designer-1358',
                    'title' => 'Junior Product Designer',
                    'company_name' => 'DesignCo',
                    'company_logo' => null,
                    'category' => 'Design',
                    'job_type' => 'contract',
                    'candidate_required_location' => 'Europe',
                    'salary' => '',
                    'description' => '<p>Design UIs</p>',
                    'publication_date' => '2026-04-09T12:00:00',
                    'tags' => ['figma'],
                ],
            ],
        ]),
    ]);

    $results = iterator_to_array((new RemotiveDriver())->fetch());

    expect($results)->toHaveCount(2);

    [$a, $b] = $results;

    expect($a->source)->toBe('remotive')
        ->and($a->externalId)->toBe('1357')
        ->and($a->title)->toBe('Senior Backend Engineer')
        ->and($a->companyName)->toBe('TechCorp')
        ->and($a->modality)->toBe(Modality::Remote)
        ->and($a->seniority)->toBe(Seniority::Senior)
        ->and($a->stack)->toBe(['python', 'django', 'postgresql'])
        ->and($a->salaryMin)->toBe(100000)
        ->and($a->salaryMax)->toBe(150000)
        ->and($a->salaryCurrency)->toBe('USD');

    expect($b->seniority)->toBe(Seniority::Junior)
        ->and($b->salaryMin)->toBeNull()
        ->and($b->salaryMax)->toBeNull();
});

it('skips items without id', function (): void {
    Http::fake([
        'remotive.com/api/remote-jobs*' => Http::response([
            'jobs' => [
                ['title' => 'No id'],
                ['id' => 1, 'url' => 'u', 'title' => 'Dev', 'company_name' => 'X', 'tags' => []],
            ],
        ]),
    ]);

    $results = iterator_to_array((new RemotiveDriver())->fetch());

    expect($results)->toHaveCount(1);
});
