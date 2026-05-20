<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionDowngraded
{
    use Dispatchable;

    public function __construct(public readonly Subscription $subscription) {}
}
