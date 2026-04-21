<?php

declare(strict_types=1);

namespace App\Domain\Metrics\Queries;

use App\Domain\Metrics\DTOs\MetricsSummaryData;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\MetricsDaily;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monta o payload do dashboard de métricas a partir de `metrics_daily`
 * (materializada) + consultas diretas para dados não agregados (funnel, heatmap, tempos).
 *
 * Queries usam `metrics_daily` para KPIs simples; só fazem fallback para
 * `applications`/`application_events` para funil, heatmap e tempo médio entre etapas.
 */
final class GetUserMetrics
{
    /**
     * Estados que contam como "resposta" do recrutador.
     */
    private const RESPONSE_STATUSES = [
        ApplicationStatus::Screening->value,
        ApplicationStatus::Assessment->value,
        ApplicationStatus::InterviewHR->value,
        ApplicationStatus::InterviewTech->value,
        ApplicationStatus::Offer->value,
        ApplicationStatus::Accepted->value,
    ];

    /**
     * Ordem canônica do funil (somente estados "positivos" / de progresso).
     *
     * @var list<ApplicationStatus>
     */
    private const FUNNEL_ORDER = [
        ApplicationStatus::Applied,
        ApplicationStatus::Screening,
        ApplicationStatus::Assessment,
        ApplicationStatus::InterviewHR,
        ApplicationStatus::InterviewTech,
        ApplicationStatus::Offer,
        ApplicationStatus::Accepted,
    ];

    public function __construct(private readonly GenerateInsights $insights) {}

    public function execute(User $user, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): MetricsSummaryData
    {
        $to ??= CarbonImmutable::now()->startOfDay();
        $from ??= $to->subDays(89);

        $kpis = $this->kpis($user, $from, $to);
        $channels = $this->channels($user, $from, $to);
        $funnel = $this->funnel($user, $from, $to);
        $heatmap = $this->heatmap($user, $from, $to);
        $avgDays = $this->avgDaysBetweenStages($user, $from, $to);

        $insights = $this->insights->execute($kpis, $channels, $funnel);

        return new MetricsSummaryData(
            kpis: $kpis,
            channels: $channels,
            funnel: $funnel,
            heatmap: $heatmap,
            avgDaysBetweenStages: $avgDays,
            insights: $insights,
            rangeFrom: $from->toDateString(),
            rangeTo: $to->toDateString(),
        );
    }

    /**
     * @return array{total_applications: int, total_responses: int, total_interviews: int, total_offers: int, total_rejections: int, response_rate: float, interview_rate: float, offer_rate: float}
     */
    private function kpis(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = MetricsDaily::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(applications_count) as apps, SUM(responses_count) as resps, SUM(interviews_count) as ints, SUM(offers_count) as offs, SUM(rejections_count) as rejs')
            ->first();

        $apps = (int) ($row->apps ?? 0);
        $resps = (int) ($row->resps ?? 0);
        $ints = (int) ($row->ints ?? 0);
        $offs = (int) ($row->offs ?? 0);
        $rejs = (int) ($row->rejs ?? 0);

        return [
            'total_applications' => $apps,
            'total_responses' => $resps,
            'total_interviews' => $ints,
            'total_offers' => $offs,
            'total_rejections' => $rejs,
            'response_rate' => $this->rate($resps, $apps),
            'interview_rate' => $this->rate($ints, $apps),
            'offer_rate' => $this->rate($offs, $apps),
        ];
    }

    /**
     * @return list<array{source: string, applications: int, responses: int, response_rate: float}>
     */
    private function channels(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $applicationsByChannel = Application::query()
            ->where('user_id', $user->id)
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotNull('source')
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        if ($applicationsByChannel->isEmpty()) {
            return [];
        }

        $responsesByChannel = DB::table('application_events as e')
            ->join('applications as a', 'a.id', '=', 'e.application_id')
            ->where('a.user_id', $user->id)
            ->whereNotNull('a.source')
            ->where('e.event_type', 'status_changed')
            ->whereBetween('e.occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['a.source', 'e.payload']);

        $responses = [];
        foreach ($responsesByChannel as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload;
            $to_ = is_array($payload) ? ($payload['to'] ?? null) : null;
            if (! is_string($to_) || ! in_array($to_, self::RESPONSE_STATUSES, true)) {
                continue;
            }
            $responses[$row->source] = ($responses[$row->source] ?? 0) + 1;
        }

        $out = [];
        foreach ($applicationsByChannel as $source => $total) {
            $sourceKey = (string) $source;
            $totalInt = (int) $total;
            $respInt = (int) ($responses[$sourceKey] ?? 0);
            $out[] = [
                'source' => $sourceKey,
                'applications' => $totalInt,
                'responses' => $respInt,
                'response_rate' => $this->rate($respInt, $totalInt),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['applications'] <=> $a['applications']);

        return $out;
    }

    /**
     * Quantas candidaturas já alcançaram cada etapa (status atual é ≥ etapa na ordem).
     *
     * @return list<array{status: string, label: string, reached: int}>
     */
    private function funnel(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $applications = Application::query()
            ->where('user_id', $user->id)
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['id', 'status']);

        if ($applications->isEmpty()) {
            return [];
        }

        /** @var array<int, string> $currentStatus */
        $currentStatus = [];
        foreach ($applications as $application) {
            // getRawOriginal bypassa o cast pro enum e devolve o valor cru da coluna (string).
            $currentStatus[(int) $application->id] = (string) $application->getRawOriginal('status');
        }

        $reachedMap = DB::table('application_events')
            ->whereIn('application_id', $applications->pluck('id'))
            ->where('event_type', 'status_changed')
            ->get(['application_id', 'payload']);

        /** @var array<int, array<string, bool>> $reachedByApp */
        $reachedByApp = [];
        foreach ($applications as $app) {
            $reachedByApp[(int) $app->id] = [ApplicationStatus::Applied->value => true];
        }
        foreach ($reachedMap as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload;
            $to_ = is_array($payload) ? ($payload['to'] ?? null) : null;
            if (is_string($to_)) {
                $reachedByApp[(int) $row->application_id][$to_] = true;
            }
        }

        $out = [];
        foreach (self::FUNNEL_ORDER as $index => $stage) {
            $count = 0;
            foreach ($reachedByApp as $appId => $reached) {
                if (isset($reached[$stage->value])) {
                    $count++;
                    continue;
                }
                // Aplicou e status atual já é posterior => considerado como alcançado.
                $currentIndex = $this->funnelIndex($currentStatus[$appId] ?? '');
                if ($currentIndex !== null && $currentIndex >= $index) {
                    $count++;
                }
            }

            $out[] = [
                'status' => $stage->value,
                'label' => $stage->label(),
                'reached' => $count,
            ];
        }

        return $out;
    }

    private function funnelIndex(string $status): ?int
    {
        foreach (self::FUNNEL_ORDER as $i => $s) {
            if ($s->value === $status) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Heatmap dia-da-semana × hora (UTC) — contagem de candidaturas aplicadas.
     *
     * @return list<array{weekday: int, hour: int, count: int}>
     */
    private function heatmap(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Application::query()
            ->where('user_id', $user->id)
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['applied_at']);

        /** @var array<int, array<int, int>> $grid */
        $grid = [];
        foreach ($rows as $row) {
            $ts = \Carbon\Carbon::parse((string) $row->applied_at);
            $weekday = (int) $ts->dayOfWeek; // 0=sunday .. 6=saturday
            $hour = (int) $ts->hour;
            $grid[$weekday][$hour] = ($grid[$weekday][$hour] ?? 0) + 1;
        }

        $out = [];
        foreach ($grid as $weekday => $hours) {
            foreach ($hours as $hour => $count) {
                $out[] = ['weekday' => $weekday, 'hour' => $hour, 'count' => $count];
            }
        }

        return $out;
    }

    /**
     * Tempo médio em dias entre a aplicação e a primeira mudança de status (resposta).
     */
    private function avgDaysBetweenStages(User $user, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $applications = Application::query()
            ->where('user_id', $user->id)
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->with(['events' => fn ($q) => $q->where('event_type', 'status_changed')->orderBy('occurred_at')])
            ->get();

        $diffs = [];
        foreach ($applications as $app) {
            /** @var \App\Models\ApplicationEvent|null $first */
            $first = $app->events->first();
            if ($first === null) {
                continue;
            }
            $applied = \Carbon\Carbon::parse((string) $app->applied_at);
            $occurred = \Carbon\Carbon::parse((string) $first->occurred_at);
            $diffs[] = $applied->diffInHours($occurred) / 24.0;
        }

        if ($diffs === []) {
            return null;
        }

        return round(array_sum($diffs) / count($diffs), 1);
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }
}
