<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Metrics\Actions\RollupDailyMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class RollupDailyMetricsCommand extends Command
{
    protected $signature = 'metrics:rollup-daily {--date= : Data alvo (YYYY-MM-DD). Default: ontem} {--from=} {--to=}';

    protected $description = 'Materializa a tabela metrics_daily com agregados por usuário (candidaturas, respostas, entrevistas, ofertas, rejeições, canais).';

    public function handle(RollupDailyMetrics $action): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            $total = $action->backfill(
                CarbonImmutable::parse($from),
                CarbonImmutable::parse($to),
            );
            $this->info("Rollup concluído: {$total} linhas no intervalo {$from} → {$to}.");

            return self::SUCCESS;
        }

        $dateOption = $this->option('date');
        $date = is_string($dateOption) && $dateOption !== ''
            ? CarbonImmutable::parse($dateOption)
            : CarbonImmutable::now()->subDay();

        $written = $action->execute($date);
        $this->info("Rollup concluído: {$written} linhas para {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
