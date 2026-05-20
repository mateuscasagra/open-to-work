<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Exceptions;

use DomainException;

final class NotSubscribedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('subscription.errors.not_subscribed'));
    }
}
