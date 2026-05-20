<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Actions;

use App\Events\SubscriptionActivated;
use App\Events\SubscriptionPastDue;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * Processa um payload de webhook do Asaas (já validado por header-token e
 * idempotência no controller). Switch por `event` e atualiza a Subscription
 * correspondente. Eventos desconhecidos são ignorados (return silencioso).
 *
 * @phpstan-type WebhookPayload array{event?: string, payment?: array<string, mixed>, subscription?: array<string, mixed>}
 */
final class ProcessAsaasWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): void
    {
        $event = is_string($payload['event'] ?? null) ? $payload['event'] : null;
        if ($event === null) {
            return;
        }

        match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->handlePaymentConfirmed($payload),
            'PAYMENT_OVERDUE' => $this->handlePaymentOverdue($payload),
            'SUBSCRIPTION_DELETED' => $this->handleSubscriptionDeleted($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePaymentConfirmed(array $payload): void
    {
        $payment = $this->arr($payload, 'payment');
        $subscriptionId = is_string($payment['subscription'] ?? null) ? $payment['subscription'] : null;
        $paymentId = is_string($payment['id'] ?? null) ? $payment['id'] : null;

        if ($subscriptionId === null) {
            Log::warning('asaas.webhook.payment_confirmed.missing_subscription', ['payment_id' => $paymentId]);

            return;
        }

        $sub = Subscription::query()->where('asaas_subscription_id', $subscriptionId)->first();
        if ($sub === null) {
            Log::warning('asaas.webhook.payment_confirmed.subscription_not_found', ['asaas_id' => $subscriptionId]);

            return;
        }

        $sub->update([
            'plan' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'last_payment_at' => now(),
            'last_payment_id' => $paymentId,
        ]);

        event(new SubscriptionActivated($sub));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePaymentOverdue(array $payload): void
    {
        $sub = $this->locateSubscription($payload);
        if ($sub === null) {
            return;
        }

        // Marca past_due mas mantém plan/period_end intactos: user ainda é Pro
        // até o período pago acabar. DowngradeExpired faz o trabalho depois.
        $sub->update(['status' => 'past_due']);

        event(new SubscriptionPastDue($sub));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleSubscriptionDeleted(array $payload): void
    {
        $sub = $this->locateSubscription($payload);
        if ($sub === null) {
            return;
        }

        if ($sub->status === 'canceled') {
            return; // idempotente
        }

        $sub->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function locateSubscription(array $payload): ?Subscription
    {
        $payment = $this->arr($payload, 'payment');
        $direct = $this->arr($payload, 'subscription');

        $subscriptionId = null;
        if (is_string($payment['subscription'] ?? null)) {
            $subscriptionId = $payment['subscription'];
        } elseif (is_string($direct['id'] ?? null)) {
            $subscriptionId = $direct['id'];
        }

        if ($subscriptionId === null) {
            return null;
        }

        return Subscription::query()->where('asaas_subscription_id', $subscriptionId)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function arr(array $payload, string $key): array
    {
        $val = $payload[$key] ?? null;

        return is_array($val) ? $val : [];
    }
}
