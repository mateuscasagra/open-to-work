<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ErrorLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class PruneErrorLogsCommand extends Command
{
    protected $signature = 'errors:prune {--days=30 : retenção em dias}';

    protected $description = 'Remove registros de error_logs mais antigos que N dias';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = CarbonImmutable::now()->subDays($days);

        $deleted = ErrorLog::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Removidos {$deleted} registros anteriores a {$cutoff->toIso8601String()}.");

        return self::SUCCESS;
    }
}
