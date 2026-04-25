<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\ArbeitnowDriver;
use App\Enums\Modality;
use Illuminate\Support\Facades\Http;

it('fetches and transforms Arbeitnow payload into JobDTOs', function (): void {
    Http::fake([
        'www.arbeitnow.com/api/job-board-api' => Http::response([
            'data' => [
                [
                    'slug' => 'senior-php-engineer-berlin-12345',
                    'title' => 'Senior PHP Engineer',
                    'company_name' => 'Acme GmbH',
                    'description' => '<p>Join us</p>',
                    'location' => 'Berlin',
                    'remote' => false,
                    'url' => 'https://www.arbeitnow.com/view/senior-php-engineer-berlin-12345',
                    'tags' => ['PHP', 'Laravel', 'Docker'],
                    'job_types' => ['full-time'],
                    'created_at' => 1733961600,
                ],
                [
                    'slug' => 'remote-react-dev-67890',
                    'title' => 'Remote React Developer',
                    'company_name' => 'Widget Inc',
                    'description' => '<p>Ship UIs</p>',
                    'location' => 'Anywhere',
                    'remote' => true,
                    'url' => 'https://www.arbeitnow.com/view/remote-react-dev-67890',
                    'tags' => ['react', 'typescript'],
                    'job_types' => ['full-time'],
                    'created_at' => 1733875200,
                ],
            ],
        ]),
    ]);

    $results = iterator_to_array((new ArbeitnowDriver)->fetch());

    expect($results)->toHaveCount(2);

    [$a, $b] = $results;

    expect($a->source)->toBe('arbeitnow')
        ->and($a->externalId)->toBe('senior-php-engineer-berlin-12345')
        ->and($a->title)->toBe('Senior PHP Engineer')
        ->and($a->companyName)->toBe('Acme GmbH')
        ->and($a->modality)->toBe(Modality::Onsite)
        ->and($a->stack)->toBe(['PHP', 'Laravel', 'Docker'])
        ->and($a->externalUrl)->toBe('https://www.arbeitnow.com/view/senior-php-engineer-berlin-12345');

    expect($b->modality)->toBe(Modality::Remote)
        ->and($b->location)->toBe('Anywhere');
});

it('skips items without slug', function (): void {
    Http::fake([
        'www.arbeitnow.com/api/job-board-api' => Http::response([
            'data' => [
                ['title' => 'No slug here'],
                ['slug' => 'ok-1', 'title' => 'Dev', 'company_name' => 'X', 'url' => 'u', 'tags' => [], 'remote' => true, 'created_at' => 1733961600],
            ],
        ]),
    ]);

    $results = iterator_to_array((new ArbeitnowDriver)->fetch());

    expect($results)->toHaveCount(1);
});

it('handles empty data array', function (): void {
    Http::fake([
        'www.arbeitnow.com/api/job-board-api' => Http::response(['data' => []]),
    ]);

    $results = iterator_to_array((new ArbeitnowDriver)->fetch());

    expect($results)->toBeEmpty();
});
