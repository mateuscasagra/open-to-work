<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionActivated
{
    use Dispatchable;

    /**
     * @param  bool  $firstActivation  true só na 1ª vez que vira Pro (free→pro).
     *                                  Renovações mensais (PAYMENT_RECEIVED com plan
     *                                  já 'pro') vêm como false — evita reenviar o
     *                                  e-mail de boas-vindas todo mês.
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly bool $firstActivation = false,
    ) {}
}
