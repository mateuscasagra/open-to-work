<?php

declare(strict_types=1);

namespace App\Domain\Admin\DTOs;

use JsonSerializable;

/**
 * Métricas agregadas do painel administrativo.
 */
final class AdminMetricsData implements JsonSerializable
{
    /**
     * @param  array{users: int, applications: int, resumes: int, active_users: int}  $totals
     * @param  list<array{user_id: int, name: string, email: string, applications_count: int}>  $topApplicants
     */
    public function __construct(
        public readonly array $totals,
        public readonly array $topApplicants,
        public readonly string $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'totals' => $this->totals,
            'top_applicants' => $this->topApplicants,
            'generated_at' => $this->generatedAt,
        ];
    }
}
