<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // Libera a configuração em dev (sem DSN) e ativa sampling conservador em prod.
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Performance: 10% das requests em prod; 100% em staging (ajustável via env).
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    'send_default_pii' => false,

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_origin' => true,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => true,
        'redis_commands' => false,
        'missing_routes' => false,
        'default_integrations' => true,
    ],

    // Por padrão relatamos apenas 4xx/5xx não esperados. Validation/404/401 ficam fora.
    'ignore_exceptions' => [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        NotFoundHttpException::class,
        ThrottleRequestsException::class,
    ],
];
