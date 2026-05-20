<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Actions;

use App\Events\SubscriptionDowngraded;
use App\Models\Subscription;

/**
 * Roda diário via scheduler: subscriptions Pro com status canceled|past_due
 * cujo current_period_end já passou viram free. Mantém asaas_customer_id pra
 * reuso em re-assinatura.
 */
final class DowngradeExpiredSubscriptions
{
    public function execute(): int
    {
        $count = 0;

        Subscription::query()
            ->where('plan', 'pro')
            ->whereIn('status', ['canceled', 'past_due'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->each(function (Subscription $sub) use (&$count): void {
                $sub->update([
                    'plan' => 'free',
                    'status' => 'active',
                    'asaas_subscription_id' => null,
                    'current_period_start' => null,
                    'current_period_end' => null,
                ]);
                event(new SubscriptionDowngraded($sub));
                $count++;
            });

        return $count;
    }
}
