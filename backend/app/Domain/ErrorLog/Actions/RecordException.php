<?php

declare(strict_types=1);

namespace App\Domain\ErrorLog\Actions;

use App\Models\ErrorLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RecordException
{
    private const MAX_MESSAGE = 1000;

    private const MAX_STACK = 8000;

    private const MAX_URL = 255;

    public function execute(Throwable $e, ?Request $request = null): void
    {
        try {
            $request ??= request();
        } catch (Throwable) {
            $request = null;
        }

        try {
            ErrorLog::query()->create([
                'level' => $this->resolveLevel($e),
                'exception_class' => mb_substr($e::class, 0, 255),
                'message' => mb_substr($e->getMessage() ?: '(empty)', 0, self::MAX_MESSAGE),
                'file' => $e->getFile() !== '' ? mb_substr($e->getFile(), 0, 255) : null,
                'line' => $e->getLine() > 0 ? $e->getLine() : null,
                'stack_trace' => mb_substr($e->getTraceAsString(), 0, self::MAX_STACK),
                'context' => null,
                'url' => $request !== null ? mb_substr($request->fullUrl(), 0, self::MAX_URL) : null,
                'method' => $request?->method(),
                'user_id' => $this->resolveUserId($request),
                'occurred_at' => Carbon::now(),
            ]);
        } catch (Throwable) {
            // Engole qualquer falha do logger — DB indisponível, schema dessincronizado etc.
            // Reportar daqui causaria loop, e Sentry/stderr já cobrem o original.
        }
    }

    private function resolveLevel(Throwable $e): string
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() < 500 ? 'warning' : 'error';
        }

        if ($e instanceof AuthorizationException || $e instanceof TokenMismatchException) {
            return 'warning';
        }

        return 'error';
    }

    private function resolveUserId(?Request $request): ?int
    {
        if ($request === null) {
            return null;
        }

        try {
            return $request->user()?->getAuthIdentifier();
        } catch (Throwable) {
            return null;
        }
    }
}
