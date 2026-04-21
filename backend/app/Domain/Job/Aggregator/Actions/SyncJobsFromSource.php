<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Actions;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\Pipeline\DeduplicateJob;
use App\Domain\Job\Aggregator\Pipeline\NormalizeJob;
use App\Domain\Job\Aggregator\Pipeline\PersistJob;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executa o pipeline de agregação para uma fonte específica.
 *
 * Fluxo:  driver->fetch()  →  Normalize  →  Deduplicate  →  Persist
 */
final class SyncJobsFromSource
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @return array{imported: int, skipped: int, failed: int}
     */
    public function execute(JobSourceDriver $driver): array
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($driver->fetch() as $dto) {
            try {
                $this->pipeline
                    ->send($dto)
                    ->through([
                        NormalizeJob::class,
                        DeduplicateJob::class,
                        PersistJob::class,
                    ])
                    ->then(fn ($job) => $job);

                $imported++;
            } catch (Throwable $e) {
                Log::error('Job aggregation failed', [
                    'source' => $driver->name(),
                    'external_id' => $dto->externalId ?? null,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return compact('imported', 'skipped', 'failed');
    }
}
