<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionCanceled;
use App\Mail\SubscriptionCanceledMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendSubscriptionCancellation
{
    public function handle(SubscriptionCanceled $event): void
    {
        $sub = $event->subscription;
        $sub->loadMissing('user');
        $user = $sub->user;

        if ($user === null) {
            return;
        }

        // Acesso Pro segue até o fim do período já pago. Se já venceu, null.
        $accessUntil = $sub->current_period_end?->isFuture() === true
            ? $sub->current_period_end->format('d/m/Y')
            : null;

        // Falha de e-mail não pode derrubar o webhook nem o request de cancelamento.
        try {
            Mail::to($user->email)
                ->locale($user->locale->value)
                ->queue(new SubscriptionCanceledMail(
                    name: $user->name,
                    accessUntil: $accessUntil,
                    actionUrl: rtrim((string) config('app.frontend_url'), '/') . '/app/plan',
                ));
        } catch (Throwable $e) {
            Log::warning('subscription.mail.canceled.failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
