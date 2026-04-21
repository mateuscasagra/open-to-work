<?php

declare(strict_types=1);

namespace App\Domain\Resume\Queries;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUserResumes
{
    /**
     * @return LengthAwarePaginator<int, Resume>
     */
    public function execute(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Resume::query()
            ->where('user_id', $user->id)
            ->withCount('sections')
            ->latest('updated_at')
            ->paginate($perPage);
    }
}
