<?php

declare(strict_types=1);

namespace App\Domain\Metrics\Actions;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\MetricsDaily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Materializa uma linha em `metrics_daily` por usuário para a data alvo.
 *
 * Agregados computados a partir de `applications.applied_at` e `application_events.occurred_at`
 * no intervalo [date 00:00, date+1 00:00).
 */
final class RollupDailyMetrics
{
    /**
     * @return int Número de linhas escritas (1 por usuário com atividade no dia).
     */
    public function execute(CarbonImmutable $date): int
    {
        $start = $date->startOfDay();
        $end = $date->addDay()->startOfDay();

        $userIds = $this->usersWithActivity($start, $end);

        $written = 0;
        foreach ($userIds as $userId) {
            $this->rollupUser((int) $userId, $date, $start, $end);
            $written++;
        }

        return $written;
    }

    /**
     * @return array<int>
     */
    private function usersWithActivity(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $fromApplications = Application::query()
            ->whereBetween('applied_at', [$start, $end])
            ->pluck('user_id');

        $fromEvents = Application::query()
            ->whereHas('events', fn ($q) => $q->whereBetween('occurred_at', [$start, $end]))
            ->pluck('user_id');

        return $fromApplications->concat($fromEvents)->unique()->values()->all();
    }

    private function rollupUser(int $userId, CarbonImmutable $date, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $appliedToday = Application::query()
            ->where('user_id', $userId)
            ->whereBetween('applied_at', [$start, $end])
            ->get();

        $responses = DB::table('application_events as e')
            ->join('applications as a', 'a.id', '=', 'e.application_id')
            ->where('a.user_id', $userId)
            ->where('e.event_type', 'status_changed')
            ->whereBetween('e.occurred_at', [$start, $end])
            ->get(['e.payload']);

        $interviews = 0;
        $offers = 0;
        $rejections = 0;
        $responsesCount = 0;

        foreach ($responses as $row) {
            /** @var array{from?: string, to?: string}|null $payload */
            $payload = is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload;
            $to = $payload['to'] ?? null;
            $from = $payload['from'] ?? null;

            if ($to === null) {
                continue;
            }

            if ($from === ApplicationStatus::Applied->value && $this->isPositiveResponse($to)) {
                $responsesCount++;
            }

            if ($to === ApplicationStatus::InterviewHR->value || $to === ApplicationStatus::InterviewTech->value) {
                $interviews++;
            }
            if ($to === ApplicationStatus::Offer->value) {
                $offers++;
            }
            if ($to === ApplicationStatus::Rejected->value) {
                $rejections++;
            }
        }

        $channels = [];
        foreach ($appliedToday as $application) {
            $source = $application->source;
            if ($source === null || $source === '') {
                continue;
            }
            $channels[$source] = ($channels[$source] ?? 0) + 1;
        }

        MetricsDaily::query()->updateOrCreate(
            ['user_id' => $userId, 'date' => $date->toDateString()],
            [
                'applications_count' => $appliedToday->count(),
                'responses_count' => $responsesCount,
                'interviews_count' => $interviews,
                'offers_count' => $offers,
                'rejections_count' => $rejections,
                'breakdown' => [
                    'channels' => $channels,
                ],
            ]
        );
    }

    /**
     * "Resposta" = primeira transição positiva a partir de `applied`.
     */
    private function isPositiveResponse(string $to): bool
    {
        return in_array($to, [
            ApplicationStatus::Screening->value,
            ApplicationStatus::Assessment->value,
            ApplicationStatus::InterviewHR->value,
            ApplicationStatus::InterviewTech->value,
            ApplicationStatus::Offer->value,
            ApplicationStatus::Accepted->value,
        ], true);
    }

    public function executeForAllUsers(CarbonImmutable $date): int
    {
        return $this->execute($date);
    }

    /**
     * Backfill para um intervalo de datas (inclusivo).
     */
    public function backfill(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $total = 0;
        $cursor = $from->startOfDay();
        $end = $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $total += $this->execute($cursor);
            $cursor = $cursor->addDay();
        }

        return $total;
    }
}
