<?php

declare(strict_types=1);

namespace App\Domain\Metrics\DTOs;

use Spatie\LaravelData\Data;

/**
 * @phpstan-type KpiArray array{total_applications: int, total_responses: int, total_interviews: int, total_offers: int, total_rejections: int, response_rate: float, interview_rate: float, offer_rate: float}
 * @phpstan-type ChannelArray array{source: string, applications: int, responses: int, response_rate: float}
 * @phpstan-type FunnelArray array{status: string, label: string, reached: int}
 * @phpstan-type HeatmapCell array{weekday: int, hour: int, count: int}
 * @phpstan-type InsightArray array{key: string, severity: string, message: string}
 * @phpstan-type MonthlyDay array{day: int, count: int}
 * @phpstan-type MonthlyArray array{year: int, month: int, days_in_month: int, days: list<MonthlyDay>, total_applications: int, total_responses: int, response_rate: float}
 */
final class MetricsSummaryData extends Data
{
    /**
     * @param  KpiArray  $kpis
     * @param  list<ChannelArray>  $channels
     * @param  list<FunnelArray>  $funnel
     * @param  list<HeatmapCell>  $heatmap
     * @param  list<InsightArray>  $insights
     * @param  MonthlyArray  $monthly
     */
    public function __construct(
        public array $kpis,
        public array $channels,
        public array $funnel,
        public array $heatmap,
        public ?float $avgDaysBetweenStages,
        public array $insights,
        public string $rangeFrom,
        public string $rangeTo,
        public array $monthly,
    ) {}
}
