<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Exceptions;

use DomainException;

final class QuotaExceededException extends DomainException
{
    public function __construct(
        public readonly int $used,
        public readonly int $limit,
        public readonly string $resetAt,
        public readonly string $plan,
    ) {
        parent::__construct(__('subscription.errors.quota_exceeded', ['limit' => $limit]));
    }
}
