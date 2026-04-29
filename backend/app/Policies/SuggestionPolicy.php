<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Suggestion;
use App\Models\User;

final class SuggestionPolicy
{
    public function delete(User $user, Suggestion $suggestion): bool
    {
        return $user->id === $suggestion->user_id;
    }
}
