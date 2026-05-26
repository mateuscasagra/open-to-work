<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionActivated
{
    use Dispatchable;

    // $firstActivation é true só na 1ª ativação (free→pro). Renovação mensal
    // (PAYMENT_RECEIVED já 'pro') vem false — não reenvia o e-mail de boas-vindas.
    public function __construct(public readonly Subscription $subscription, public readonly bool $firstActivation = false) {}
}
