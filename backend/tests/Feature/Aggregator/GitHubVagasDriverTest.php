<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\GitHubVagasDriver;
use App\Enums\Modality;
use App\Enums\Seniority;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('aggregator.github_repos', [
        ['owner' => 'frontendbr', 'repo' => 'vagas'],
    ]);
});

it('fetches issues from GitHub repos and maps to JobDTO', function (): void {
    Http::fake([
        'api.github.com/repos/frontendbr/vagas/issues*' => Http::sequence()
            ->push([
                [
                    'number' => 4321,
                    'title' => '[São Paulo / SP] [Híbrido] Acme Tech - Pessoa Desenvolvedora Backend Sênior (Node.js)',
                    'body' => "## Sobre a vaga\n\nTrabalhe com Node.js, PostgreSQL e AWS.\n\nContato: rh@acme.com",
                    'html_url' => 'https://github.com/frontendbr/vagas/issues/4321',
                    'created_at' => '2026-04-10T12:00:00Z',
                ],
                [
                    'number' => 4322,
                    'title' => '[Remoto] CocoaCo — Estágio iOS',
                    'body' => '## Vaga\n\nSwift, Kotlin opcional.',
                    'html_url' => 'https://github.com/frontendbr/vagas/issues/4322',
                    'created_at' => '2026-04-09T08:00:00Z',
                ],
                [
                    // Pull request — deve ser ignorado.
                    'number' => 4323,
                    'title' => 'Fix typo in README',
                    'body' => '',
                    'html_url' => 'https://github.com/frontendbr/vagas/pull/4323',
                    'created_at' => '2026-04-08T08:00:00Z',
                    'pull_request' => ['url' => 'https://api.github.com/...'],
                ],
            ])
            ->push([]),
    ]);

    $results = iterator_to_array((new GitHubVagasDriver)->fetch(), false);

    expect($results)->toHaveCount(2);

    [$a, $b] = $results;

    expect($a->source)->toBe('github_vagas')
        ->and($a->externalId)->toBe('frontendbr/vagas#4321')
        ->and($a->externalUrl)->toBe('https://github.com/frontendbr/vagas/issues/4321')
        ->and($a->companyName)->toBe('Acme Tech')
        ->and($a->title)->toBe('Pessoa Desenvolvedora Backend Sênior (Node.js)')
        ->and($a->modality)->toBe(Modality::Hybrid)
        ->and($a->seniority)->toBe(Seniority::Senior)
        ->and($a->location)->toBe('São Paulo / SP')
        ->and($a->language)->toBe('pt_BR')
        ->and($a->stack)->toContain('node', 'postgresql', 'aws');

    expect($b->modality)->toBe(Modality::Remote)
        ->and($b->seniority)->toBe(Seniority::Intern)
        ->and($b->companyName)->toBe('CocoaCo')
        ->and($b->title)->toBe('Estágio iOS')
        ->and($b->location)->toBeNull()
        ->and($b->stack)->toContain('swift', 'kotlin');
});

it('skips issues without number/title/html_url', function (): void {
    Http::fake([
        'api.github.com/repos/frontendbr/vagas/issues*' => Http::response([
            ['title' => 'sem number'],
            [
                'number' => 1,
                'title' => '[Remoto] Empresa - Cargo',
                'html_url' => 'https://github.com/frontendbr/vagas/issues/1',
                'created_at' => '2026-04-10T12:00:00Z',
                'body' => '',
            ],
        ]),
    ]);

    $results = iterator_to_array((new GitHubVagasDriver)->fetch(), false);

    expect($results)->toHaveCount(1);
});

it('continues when one repo fails', function (): void {
    config()->set('aggregator.github_repos', [
        ['owner' => 'doesnt-exist', 'repo' => 'vagas'],
        ['owner' => 'frontendbr', 'repo' => 'vagas'],
    ]);

    Http::fake([
        'api.github.com/repos/doesnt-exist/vagas/issues*' => Http::response('Not Found', 404),
        'api.github.com/repos/frontendbr/vagas/issues*' => Http::response([
            [
                'number' => 1,
                'title' => '[Remoto] Empresa - Cargo',
                'html_url' => 'https://github.com/frontendbr/vagas/issues/1',
                'created_at' => '2026-04-10T12:00:00Z',
                'body' => '',
            ],
        ]),
    ]);

    $results = iterator_to_array((new GitHubVagasDriver)->fetch(), false);

    expect($results)->toHaveCount(1);
});

it('falls back when title has no [tags] or no separator', function (): void {
    Http::fake([
        'api.github.com/repos/frontendbr/vagas/issues*' => Http::response([
            [
                'number' => 1,
                'title' => 'Cargo sem brackets nem hífen',
                'html_url' => 'https://github.com/frontendbr/vagas/issues/1',
                'created_at' => '2026-04-10T12:00:00Z',
                'body' => '',
            ],
        ]),
    ]);

    $results = iterator_to_array((new GitHubVagasDriver)->fetch(), false);

    expect($results)->toHaveCount(1);
    expect($results[0]->title)->toBe('Cargo sem brackets nem hífen')
        ->and($results[0]->companyName)->toBe('Comunidade GitHub')
        ->and($results[0]->modality)->toBeNull()
        ->and($results[0]->location)->toBeNull();
});
