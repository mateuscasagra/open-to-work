<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionActivated;
use App\Mail\SubscriptionConfirmedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendSubscriptionConfirmation
{
    public function handle(SubscriptionActivated $event): void
    {
        // Só na 1ª ativação (free→pro). Renovação mensal não reenvia boas-vindas.
        if (! $event->firstActivation) {
            return;
        }

        $sub = $event->subscription;
        $sub->loadMissing('user');
        $user = $sub->user;

        if ($user === null) {
            return;
        }

        // Falha de e-mail não pode derrubar o webhook (Asaas re-tentaria à toa).
        try {
            Mail::to($user->email)
                ->locale($user->locale?->value ?? (string) config('app.locale'))
                ->queue(new SubscriptionConfirmedMail(
                    name: $user->name,
                    renewsAt: $sub->current_period_end?->format('d/m/Y') ?? '',
                    actionUrl: rtrim((string) config('app.frontend_url'), '/') . '/app',
                ));
        } catch (Throwable $e) {
            Log::warning('subscription.mail.confirmed.failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
