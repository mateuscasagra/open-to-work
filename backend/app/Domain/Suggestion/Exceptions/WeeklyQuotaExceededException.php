<?php

declare(strict_types=1);

namespace App\Domain\Suggestion\Exceptions;

use Carbon\CarbonInterface;
use DomainException;

final class WeeklyQuotaExceededException extends DomainException
{
    public function __construct(public readonly CarbonInterface $nextSlotAt)
    {
        parent::__construct(__('suggestions.errors.weekly_quota_exceeded', ['limit' => 5]));
    }
}
