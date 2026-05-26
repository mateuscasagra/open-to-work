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
     * @param  array{
     *     active: int,
     *     pending: int,
     *     canceled: int,
     *     cancellation_rate: float,
     *     mrr_cents: int,
     *     total_revenue_cents: int
     * }  $subscriptions
     * @param  list<array{user_id: int, name: string, email: string, applications_count: int}>  $topApplicants
     * @param  array{
     *     countries: list<array{country_code: string, count: int}>,
     *     states: list<array{country_code: string, state_code: ?string, state_name: ?string, count: int}>,
     *     cities: list<array{country_code: string, city: string, count: int}>,
     *     without_location: int
     * }  $byLocation
     */
    public function __construct(
        public readonly array $totals,
        public readonly array $subscriptions,
        public readonly array $topApplicants,
        public readonly array $byLocation,
        public readonly string $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'totals' => $this->totals,
            'subscriptions' => $this->subscriptions,
            'top_applicants' => $this->topApplicants,
            'by_location' => $this->byLocation,
            'generated_at' => $this->generatedAt,
        ];
    }
}
