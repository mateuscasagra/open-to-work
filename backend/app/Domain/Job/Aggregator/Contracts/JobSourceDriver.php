<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Contracts;

use App\Domain\Job\Aggregator\DTOs\JobDTO;

/**
 * Strategy contract: cada fonte de vagas (RemoteOK, Arbeitnow, Gupy, etc.)
 * implementa esta interface e é registrada no container.
 */
interface JobSourceDriver
{
    /**
     * Identificador estável da fonte (ex: "remote_ok").
     */
    public function name(): string;

    /**
     * Busca vagas da fonte e retorna como DTOs não-normalizados.
     * Deve ser lazy (iterable) para não estourar memória com fontes grandes.
     *
     * @return iterable<JobDTO>
     */
    public function fetch(): iterable;
}
