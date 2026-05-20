<?php

declare(strict_types=1);

namespace App\Domain\Subscription\DTOs;

use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class QuotaData extends Data
{
    /**
     * Limite de candidaturas por mês calendário no plano free.
     * Fonte única de verdade — usado tanto na projeção pra UI quanto
     * na regra de negócio em CreateApplication.
     */
    public const FREE_MONTHLY_LIMIT = 15;

    /**
     * Timezone alvo do "mês calendário". Hard-coded em vez de
     * config('app.timezone') porque app.timezone hoje é UTC para
     * scheduler/logs; quota é regra de produto BR.
     */
    public const TIMEZONE = 'America/Sao_Paulo';

    public function __construct(
        public int $used,
        public ?int $limit,    // null = ilimitado (Pro)
        public string $reset_at,
        public string $plan,
    ) {}

    public static function fromUser(User $user): self
    {
        $isPro = $user->isPro();

        $monthStart = self::monthStart();
        $used = $user->applications()
            ->where('applied_at', '>=', $monthStart)
            ->count();

        return new self(
            used: $used,
            limit: $isPro ? null : self::FREE_MONTHLY_LIMIT,
            reset_at: self::nextMonthStart()->toIso8601String(),
            plan: $isPro ? 'pro' : 'free',
        );
    }

    public static function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfMonth()->utc();
    }

    public static function nextMonthStart(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->addMonth()->startOfMonth()->utc();
    }
}
