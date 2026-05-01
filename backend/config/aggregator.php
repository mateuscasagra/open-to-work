<?php

declare(strict_types=1);

use App\Domain\Job\Aggregator\Drivers\ArbeitnowDriver;
use App\Domain\Job\Aggregator\Drivers\GitHubVagasDriver;
use App\Domain\Job\Aggregator\Drivers\RemoteOkDriver;
use App\Domain\Job\Aggregator\Drivers\RemotiveDriver;
use App\Domain\Job\Aggregator\Drivers\WeWorkRemotelyDriver;

return [
    'cron' => env('AGGREGATOR_SCHEDULE_CRON', '0 */6 * * *'),

    /*
    |--------------------------------------------------------------------------
    | Fontes habilitadas
    |--------------------------------------------------------------------------
    |
    | Lista de chaves que devem rodar no schedule. Override via
    | AGGREGATOR_SOURCES=remote_ok,arbeitnow no .env.
    */
    'enabled_sources' => array_values(array_filter(
        explode(',', (string) env(
            'AGGREGATOR_SOURCES',
            'remote_ok,arbeitnow,remotive,we_work_remotely,github_vagas'
        ))
    )),

    /*
    |--------------------------------------------------------------------------
    | Map de drivers
    |--------------------------------------------------------------------------
    |
    | Uma chave por fonte. Para adicionar uma nova fonte basta implementar
    | JobSourceDriver e registrá-la aqui.
    |
    | @var array<string, class-string<\App\Domain\Job\Aggregator\Contracts\JobSourceDriver>>
    */
    'drivers' => [
        'remote_ok' => RemoteOkDriver::class,
        'arbeitnow' => ArbeitnowDriver::class,
        'remotive' => RemotiveDriver::class,
        'we_work_remotely' => WeWorkRemotelyDriver::class,
        'github_vagas' => GitHubVagasDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Repositórios GitHub para o GitHubVagasDriver
    |--------------------------------------------------------------------------
    |
    | Lista de comunidades GitHub que publicam vagas como Issues. Cada issue
    | aberta = 1 vaga. O driver pagina até 5 páginas (500 issues) por repo.
    */
    'github_repos' => [
        ['owner' => 'frontendbr', 'repo' => 'vagas'],
        ['owner' => 'backend-br', 'repo' => 'vagas'],
        ['owner' => 'react-brasil', 'repo' => 'vagas'],
        ['owner' => 'androiddevbr', 'repo' => 'vagas'],
        ['owner' => 'CocoaHeadsBrasil', 'repo' => 'vagas'],
        ['owner' => 'vuejs-brasil', 'repo' => 'vagas'],
    ],
];
