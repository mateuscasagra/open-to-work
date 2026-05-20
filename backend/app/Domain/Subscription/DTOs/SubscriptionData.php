<?php

declare(strict_types=1);

namespace App\Domain\Subscription\DTOs;

use App\Models\Plan;
use App\Models\User;
use Spatie\LaravelData\Data;

final class SubscriptionData extends Data
{
    public function __construct(
        public string $plan,                  // 'free' | 'pro'
        public string $status,                // 'active' | 'canceled' | 'past_due'
        public ?string $current_period_end,   // ISO8601
        public ?string $canceled_at,          // ISO8601
        public int $pro_price_cents,          // preço atual do Pro (lido da tabela `plans`)
        public QuotaData $quota,
    ) {}

    public static function fromUser(User $user): self
    {
        $user->loadMissing('subscription');
        $sub = $user->subscription;

        // Usa effectivePlan() pra evitar mostrar 'pro' quando o período já venceu
        // mas o scheduler de downgrade ainda não rodou. UI vê estado coerente.
        $effectivePlan = $sub?->effectivePlan() ?? 'free';

        return new self(
            plan: $effectivePlan,
            status: $sub?->status ?? 'active',
            current_period_end: $sub?->current_period_end?->toIso8601String(),
            canceled_at: $sub?->canceled_at?->toIso8601String(),
            pro_price_cents: Plan::priceCentsBySlug('pro') ?? 2500,
            quota: QuotaData::fromUser($user),
        );
    }
}
