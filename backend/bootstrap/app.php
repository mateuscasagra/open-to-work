<?php

declare(strict_types=1);

use App\Domain\ErrorLog\Actions\RecordException;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->api(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry: reporta exceções não ignoradas via Integration do sentry-laravel.
        // O DSN é lido de SENTRY_LARAVEL_DSN; sem DSN vira no-op (seguro para dev/tests).
        Integration::handles($exceptions);

        // Classes que estão EXATAS no internalDontReport — stopIgnoring funciona.
        $exceptions->stopIgnoring([
            AuthorizationException::class,
            TokenMismatchException::class,
        ]);

        // HttpException subclasses (403/429 do Symfony) são bloqueadas no shouldntReport
        // por instanceof — só dá pra alcançar via render callback.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();
            if ($status >= 500 || in_array($status, [403, 419, 429], true)) {
                app(RecordException::class)->execute($e, $request);
            }

            return null;
        });

        // Captura tudo que sobrou — uncaught throwables, jobs, scheduled tasks.
        $exceptions->report(function (Throwable $e): void {
            app(RecordException::class)->execute($e);
        });
    })->create();
