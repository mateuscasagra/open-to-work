<?php

declare(strict_types=1);

namespace App\Domain\Suggestion\Exceptions;

use DomainException;
use Illuminate\Support\Carbon;

final class WeeklyQuotaExceededException extends DomainException
{
    public function __construct(public readonly Carbon $nextSlotAt)
    {
        parent::__construct(__('suggestions.errors.weekly_quota_exceeded', ['limit' => 5]));
    }
}
