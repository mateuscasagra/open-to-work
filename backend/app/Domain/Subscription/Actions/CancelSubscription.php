<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Actions;

use App\Domain\Subscription\Contracts\AsaasGateway;
use App\Domain\Subscription\Exceptions\NotSubscribedException;
use App\Events\SubscriptionCanceled;
use App\Models\User;

/**
 * Cancela a subscription no Asaas e marca canceled_at no DB. Mantém plan=pro
 * e current_period_end intactos — usuário fica Pro até o fim do período pago,
 * só vira free quando o command DowngradeExpiredSubscriptions roda.
 */
final class CancelSubscription
{
    public function __construct(
        private readonly AsaasGateway $asaas,
    ) {}

    public function execute(User $user): void
    {
        $user->loadMissing('subscription');
        $sub = $user->subscription;

        if ($sub === null || $sub->asaas_subscription_id === null) {
            throw new NotSubscribedException;
        }

        // AsaasGateway trata 404 internamente (idempotente).
        $this->asaas->cancelSubscription($sub->asaas_subscription_id);

        $sub->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        // O webhook SUBSCRIPTION_DELETED chega depois, mas handleSubscriptionDeleted
        // é idempotente (status já 'canceled' → não redispara). E-mail sai uma vez só.
        event(new SubscriptionCanceled($sub));
    }
}
