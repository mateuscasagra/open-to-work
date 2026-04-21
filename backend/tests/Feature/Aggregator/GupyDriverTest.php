<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\GupyDriver;
use App\Enums\Modality;
use App\Enums\Seniority;
use Illuminate\Support\Facades\Http;

it('fetches and transforms Gupy payload into JobDTOs', function (): void {
    Http::fake([
        'portal.api.gupy.io/*' => Http::sequence()
            ->push([
                'data' => [
                    [
                        'id' => 9001,
                        'name' => 'Pessoa Desenvolvedora Backend Sênior (Node.js)',
                        'careerPageName' => 'Gupy Clientes',
                        'careerPageLogo' => 'https://gupy.io/logo.png',
                        'description' => '<p>Trabalhe com Node.js, PostgreSQL e AWS.</p>',
                        'jobUrl' => 'https://empresa.gupy.io/jobs/9001',
                        'isRemoteWork' => true,
                        'workplaceType' => 'remote',
                        'city' => 'São Paulo',
                        'state' => 'SP',
                        'country' => 'Brasil',
                        'publishedDate' => '2026-04-10T12:00:00Z',
                    ],
                    [
                        'id' => 9002,
                        'name' => 'Analista de Dados Júnior',
                        'careerPageName' => 'Outra Empresa',
                        'description' => '<p>Python e SQL.</p>',
                        'jobUrl' => 'https://outraempresa.gupy.io/jobs/9002',
                        'isRemoteWork' => false,
                        'workplaceType' => 'hybrid',
                        'city' => 'Rio de Janeiro',
                        'state' => 'RJ',
                        'country' => 'Brasil',
                        'publishedDate' => '2026-04-09T08:00:00Z',
                    ],
                ],
            ])
            ->push(['data' => []]),
    ]);

    $results = iterator_to_array((new GupyDriver())->fetch());

    expect($results)->toHaveCount(2);

    [$a, $b] = $results;

    expect($a->source)->toBe('gupy')
        ->and($a->externalId)->toBe('9001')
        ->and($a->title)->toBe('Pessoa Desenvolvedora Backend Sênior (Node.js)')
        ->and($a->companyName)->toBe('Gupy Clientes')
        ->and($a->modality)->toBe(Modality::Remote)
        ->and($a->seniority)->toBe(Seniority::Senior)
        ->and($a->location)->toBe('São Paulo, SP, Brasil')
        ->and($a->language)->toBe('pt')
        ->and($a->stack)->toContain('node', 'postgresql', 'aws');

    expect($b->modality)->toBe(Modality::Hybrid)
        ->and($b->seniority)->toBe(Seniority::Junior)
        ->and($b->location)->toBe('Rio de Janeiro, RJ, Brasil')
        ->and($b->stack)->toContain('python');
});

it('skips items without id', function (): void {
    Http::fake([
        'portal.api.gupy.io/*' => Http::response([
            'data' => [
                ['name' => 'Sem id'],
                [
                    'id' => 1,
                    'name' => 'Dev',
                    'careerPageName' => 'X',
                    'jobUrl' => 'https://x.gupy.io/jobs/1',
                    'isRemoteWork' => true,
                ],
            ],
        ]),
    ]);

    $results = iterator_to_array((new GupyDriver())->fetch());

    expect($results)->toHaveCount(1);
});

it('stops paginating when page returns fewer items than page size', function (): void {
    Http::fake([
        'portal.api.gupy.io/*' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Single Job',
                    'careerPageName' => 'Empresa',
                    'jobUrl' => 'https://x.gupy.io/jobs/1',
                    'isRemoteWork' => true,
                ],
            ],
        ]),
    ]);

    $results = iterator_to_array((new GupyDriver())->fetch());

    expect($results)->toHaveCount(1);

    Http::assertSentCount(1);
});
