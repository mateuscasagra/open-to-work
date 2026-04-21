<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Resume;
use App\Models\User;

final class ResumePolicy
{
    public function view(User $user, Resume $resume): bool
    {
        return $resume->user_id === $user->id;
    }

    public function update(User $user, Resume $resume): bool
    {
        return $resume->user_id === $user->id;
    }

    public function delete(User $user, Resume $resume): bool
    {
        return $resume->user_id === $user->id;
    }
}
