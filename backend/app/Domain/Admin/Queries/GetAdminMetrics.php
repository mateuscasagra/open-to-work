<?php

declare(strict_types=1);

namespace App\Domain\Admin\Queries;

use App\Domain\Admin\DTOs\AdminMetricsData;
use App\Models\Application;
use App\Models\Resume;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Agrega métricas globais do produto para o painel administrativo.
 */
final class GetAdminMetrics
{
    /**
     * Mínimo de candidaturas na última semana para considerar o usuário ativo.
     */
    public const ACTIVE_USER_MIN_APPLICATIONS = 3;

    /**
     * Janela (em dias) para contagem de candidaturas em "última semana".
     */
    public const ACTIVE_WINDOW_DAYS = 7;

    /**
     * Tamanho do ranking de top candidatos.
     */
    public const TOP_APPLICANTS_LIMIT = 5;

    public function __construct(
        private readonly GetUserLocationDistribution $locationDistribution,
        private readonly GetSubscriptionStats $subscriptionStats,
    ) {}

    public function execute(?CarbonImmutable $now = null): AdminMetricsData
    {
        $now ??= CarbonImmutable::now();
        $weekAgo = $now->subDays(self::ACTIVE_WINDOW_DAYS);

        $totalUsers = User::query()->count();
        $totalApplications = Application::query()->count();
        $totalResumes = Resume::query()->count();
        $activeUsers = $this->countActiveUsers($weekAgo, $now);
        $topApplicants = $this->topApplicants();
        $byLocation = $this->locationDistribution->execute();
        $subscriptions = $this->subscriptionStats->execute();

        return new AdminMetricsData(
            totals: [
                'users' => $totalUsers,
                'applications' => $totalApplications,
                'resumes' => $totalResumes,
                'active_users' => $activeUsers,
            ],
            subscriptions: $subscriptions,
            topApplicants: $topApplicants,
            byLocation: $byLocation,
            generatedAt: $now->toIso8601String(),
        );
    }

    private function countActiveUsers(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return Application::query()
            ->whereBetween('applied_at', [$from, $to])
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [self::ACTIVE_USER_MIN_APPLICATIONS])
            ->get()
            ->count();
    }

    /**
     * @return list<array{user_id: int, name: string, email: string, applications_count: int}>
     */
    private function topApplicants(): array
    {
        $rows = DB::table('users')
            ->join('applications', 'applications.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(applications.id) as applications_count')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('applications_count')
            ->orderBy('users.id')
            ->limit(self::TOP_APPLICANTS_LIMIT)
            ->get();

        return $rows->map(fn ($row): array => [
            'user_id' => (int) $row->id,
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'applications_count' => (int) $row->applications_count,
        ])->all();
    }
}
