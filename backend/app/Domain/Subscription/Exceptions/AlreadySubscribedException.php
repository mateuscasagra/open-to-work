<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Exceptions;

use DomainException;

final class AlreadySubscribedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('subscription.errors.already_subscribed'));
    }
}
