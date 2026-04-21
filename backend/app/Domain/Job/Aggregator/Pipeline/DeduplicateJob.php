<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Pipeline;

use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Models\Job;
use Closure;

/**
 * Estágio 2: decide se a vaga já existe (mesmo hash canônico).
 * Se existir, passa o ID do job existente adiante para que o PersistJob apenas
 * anexe um novo job_source em vez de duplicar.
 */
final class DeduplicateJob
{
    public function handle(JobDTO $job, Closure $next): mixed
    {
        $existing = Job::query()
            ->where('canonical_hash', $job->canonicalHash())
            ->first();

        // passa tupla [DTO, Job|null] adiante
        return $next([$job, $existing]);
    }
}
