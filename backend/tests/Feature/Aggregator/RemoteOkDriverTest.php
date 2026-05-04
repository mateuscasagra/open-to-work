<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\RemoteOkDriver;
use Illuminate\Support\Facades\Http;

it('fetches and transforms RemoteOK payload into JobDTOs', function (): void {
    Http::fake([
        'remoteok.com/api' => Http::response([
            ['legal' => 'meta ignored'],
            [
                'id' => 'ok-1',
                'position' => 'Senior PHP Developer',
                'company' => 'Acme',
                'company_logo' => 'https://example.com/logo.png',
                'description' => '<p>Build APIs</p>',
                'location' => 'Remote',
                'tags' => ['php', 'laravel'],
                'salary_min' => 80000,
                'salary_max' => 120000,
                'url' => 'https://remoteok.com/remote-jobs/ok-1',
                'date' => '2026-04-10T00:00:00+00:00',
            ],
        ]),
    ]);

    $driver = new RemoteOkDriver;
    $results = iterator_to_array($driver->fetch());

    expect($results)->toHaveCount(1);
    $dto = $results[0];
    expect($dto->title)->toBe('Senior PHP Developer')
        ->and($dto->companyName)->toBe('Acme')
        ->and($dto->stack)->toBe(['php', 'laravel'])
        ->and($dto->source)->toBe('remote_ok');
});

it('skips items without id (meta rows)', function (): void {
    Http::fake([
        'remoteok.com/api' => Http::response([
            ['no_id_here' => true],
            ['no_id_here' => 'another'],
        ]),
    ]);

    $results = iterator_to_array((new RemoteOkDriver)->fetch());

    expect($results)->toBeEmpty();
});

it('retries on transient failure then succeeds', function (): void {
    Http::fake([
        'remoteok.com/api' => Http::sequence()
            ->push('server error', 500)
            ->push([
                ['legal' => 'meta'],
                ['id' => 1, 'position' => 'Backend Developer', 'company' => 'X', 'tags' => ['python']],
            ]),
    ]);

    $results = iterator_to_array((new RemoteOkDriver)->fetch());

    expect($results)->toHaveCount(1);
});

it('descarta vagas que não são de programação', function (): void {
    Http::fake([
        'remoteok.com/api' => Http::response([
            ['legal' => 'meta'],
            [
                'id' => 'tech-1',
                'position' => 'Senior PHP Developer',
                'company' => 'Acme',
                'tags' => ['php', 'laravel'],
                'url' => 'https://remoteok.com/remote-jobs/tech-1',
            ],
            [
                'id' => 'mkt-1',
                'position' => 'Senior Marketing Manager',
                'company' => 'GrowthCo',
                'tags' => ['seo', 'content'],
                'url' => 'https://remoteok.com/remote-jobs/mkt-1',
            ],
            [
                'id' => 'design-1',
                'position' => 'Product Designer',
                'company' => 'PixelCo',
                'tags' => ['figma', 'ux'],
                'url' => 'https://remoteok.com/remote-jobs/design-1',
            ],
        ]),
    ]);

    $results = iterator_to_array((new RemoteOkDriver)->fetch());

    expect($results)->toHaveCount(1)
        ->and($results[0]->externalId)->toBe('tech-1');
});
