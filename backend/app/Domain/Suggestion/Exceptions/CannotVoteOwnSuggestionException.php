<?php

declare(strict_types=1);

namespace App\Domain\Suggestion\Exceptions;

use DomainException;

final class CannotVoteOwnSuggestionException extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('suggestions.errors.cannot_vote_own'));
    }
}
