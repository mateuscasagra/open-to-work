<?php

declare(strict_types=1);

namespace App\Domain\Metrics\Queries;

use App\Domain\Metrics\DTOs\MetricsSummaryData;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monta o payload do dashboard de métricas em tempo real a partir de
 * `applications` e `application_events`.
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

    public function execute(User $user, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, string $timezone = 'UTC'): MetricsSummaryData
    {
        $to ??= CarbonImmutable::now()->startOfDay();
        $from ??= $to->subDays(89);

        $kpis = $this->kpis($user, $from, $to);
        $channels = $this->channels($user, $from, $to);
        $funnel = $this->funnel($user, $from, $to);
        $heatmap = $this->heatmap($user, $from, $to, $timezone);
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
        $apps = Application::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->count();

        $events = DB::table('application_events as e')
            ->join('applications as a', 'a.id', '=', 'e.application_id')
            ->where('a.user_id', $user->id)
            ->whereNull('a.archived_at')
            ->where('e.event_type', 'status_changed')
            ->whereBetween('e.occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['e.payload']);

        $resps = 0;
        $ints = 0;
        $offs = 0;
        $rejs = 0;

        foreach ($events as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload;
            $toStatus = is_array($payload) ? ($payload['to'] ?? null) : null;
            $fromStatus = is_array($payload) ? ($payload['from'] ?? null) : null;

            if (! is_string($toStatus)) {
                continue;
            }

            if ($fromStatus === ApplicationStatus::Applied->value && in_array($toStatus, self::RESPONSE_STATUSES, true)) {
                $resps++;
            }
            if ($toStatus === ApplicationStatus::InterviewHR->value || $toStatus === ApplicationStatus::InterviewTech->value) {
                $ints++;
            }
            if ($toStatus === ApplicationStatus::Offer->value) {
                $offs++;
            }
            if ($toStatus === ApplicationStatus::Rejected->value) {
                $rejs++;
            }
        }

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
            ->whereNull('archived_at')
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
            ->whereNull('a.archived_at')
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
            ->whereNull('archived_at')
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
     * Heatmap dia-da-semana × hora — contagem de candidaturas aplicadas no timezone do user.
     *
     * @return list<array{weekday: int, hour: int, count: int}>
     */
    private function heatmap(User $user, CarbonImmutable $from, CarbonImmutable $to, string $timezone = 'UTC'): array
    {
        $rows = Application::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['applied_at']);

        /** @var array<int, array<int, int>> $grid */
        $grid = [];
        foreach ($rows as $row) {
            $ts = Carbon::parse((string) $row->applied_at)->setTimezone($timezone);
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
            ->whereNull('archived_at')
            ->whereBetween('applied_at', [$from->startOfDay(), $to->endOfDay()])
            ->with(['events' => fn ($q) => $q->where('event_type', 'status_changed')->orderBy('occurred_at')])
            ->get();

        $diffs = [];
        foreach ($applications as $app) {
            /** @var ApplicationEvent|null $first */
            $first = $app->events->first();
            if ($first === null) {
                continue;
            }
            $applied = Carbon::parse((string) $app->applied_at);
            $occurred = Carbon::parse((string) $first->occurred_at);
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
