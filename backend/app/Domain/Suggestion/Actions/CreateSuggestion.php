<?php

declare(strict_types=1);

namespace App\Domain\Suggestion\Actions;

use App\Domain\Suggestion\DTOs\SuggestionData;
use App\Domain\Suggestion\Exceptions\WeeklyQuotaExceededException;
use App\Models\Suggestion;
use App\Models\User;
use Carbon\CarbonInterface;

final class CreateSuggestion
{
    public const WEEKLY_LIMIT = 5;

    public const WINDOW_DAYS = 7;

    public function execute(User $user, SuggestionData $data): Suggestion
    {
        $windowStart = now()->subDays(self::WINDOW_DAYS);

        $recent = Suggestion::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->get(['created_at']);

        if ($recent->count() >= self::WEEKLY_LIMIT) {
            $oldest = $recent->first();
            assert($oldest instanceof Suggestion);
            $createdAt = $oldest->created_at;
            assert($createdAt instanceof CarbonInterface);

            throw new WeeklyQuotaExceededException(
                $createdAt->copy()->addDays(self::WINDOW_DAYS)
            );
        }

        return Suggestion::query()->create([
            'user_id' => $user->id,
            'title' => $data->title,
            'body' => $data->body,
            'upvotes_count' => 0,
            'downvotes_count' => 0,
            'score' => 0,
        ]);
    }
}
