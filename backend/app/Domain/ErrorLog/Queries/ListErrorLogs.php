<?php

declare(strict_types=1);

namespace App\Domain\ErrorLog\Queries;

use App\Domain\ErrorLog\DTOs\ErrorLogData;
use App\Models\ErrorLog;
use Carbon\CarbonImmutable;

final class ListErrorLogs
{
    public const DEFAULT_LIMIT = 50;

    public const MAX_LIMIT = 200;

    public function execute(int $limit = self::DEFAULT_LIMIT, ?string $level = null): ErrorLogData
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query = ErrorLog::query()->with('user:id,name,email');

        if ($level !== null && in_array($level, ['error', 'warning'], true)) {
            $query->where('level', $level);
        }

        $rows = $query
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        $total = ErrorLog::query()->count();

        $items = array_values($rows->map(fn (ErrorLog $row): array => [
            'id' => $row->id,
            'level' => $row->level,
            'exception_class' => $row->exception_class,
            'message' => $row->message,
            'file' => $row->file,
            'line' => $row->line,
            'url' => $row->url,
            'method' => $row->method,
            'stack_trace' => $row->stack_trace,
            'user' => $row->user !== null ? [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'email' => $row->user->email,
            ] : null,
            'occurred_at' => $row->occurred_at->toIso8601String(),
        ])->all());

        return new ErrorLogData(
            items: $items,
            total: $total,
            generatedAt: CarbonImmutable::now()->toIso8601String(),
        );
    }
}
