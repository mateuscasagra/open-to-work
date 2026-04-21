<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Job\Aggregator\Actions\SyncJobsFromSource;
use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;

final class AggregateJobsCommand extends Command
{
    protected $signature = 'jobs:aggregate {source?* : fontes a sincronizar (padrão: todas habilitadas)}';

    protected $description = 'Sincroniza vagas das fontes configuradas';

    public function handle(Container $container, SyncJobsFromSource $action): int
    {
        $sources = $this->argument('source') ?: config('aggregator.enabled_sources', []);

        if (empty($sources)) {
            $this->warn('Nenhuma fonte habilitada. Configure AGGREGATOR_SOURCES.');

            return self::SUCCESS;
        }

        $totalImported = 0;

        foreach ($sources as $source) {
            $this->info("→ {$source}");

            try {
                /** @var JobSourceDriver $driver */
                $driver = $container->make("job.driver.{$source}");
            } catch (\Throwable $e) {
                $this->error("  Driver não encontrado: {$source}");

                continue;
            }

            $stats = $action->execute($driver);
            $totalImported += $stats['imported'];

            $this->line("  imported={$stats['imported']} failed={$stats['failed']}");
        }

        $this->newLine();
        $this->info("Total: {$totalImported} vagas sincronizadas.");

        return self::SUCCESS;
    }
}
