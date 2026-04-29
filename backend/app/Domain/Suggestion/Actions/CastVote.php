<?php

declare(strict_types=1);

namespace App\Domain\Suggestion\Actions;

use App\Domain\Suggestion\Exceptions\CannotVoteOwnSuggestionException;
use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CastVote
{
    public function execute(User $user, Suggestion $suggestion, int $value): ?SuggestionVote
    {
        if ($value !== 1 && $value !== -1) {
            throw new InvalidArgumentException('Vote value must be 1 or -1.');
        }

        if ($user->id === $suggestion->user_id) {
            throw new CannotVoteOwnSuggestionException;
        }

        return DB::transaction(function () use ($user, $suggestion, $value): ?SuggestionVote {
            $existing = SuggestionVote::query()
                ->where('user_id', $user->id)
                ->where('suggestion_id', $suggestion->id)
                ->lockForUpdate()
                ->first();

            $vote = null;

            if ($existing === null) {
                $vote = SuggestionVote::query()->create([
                    'user_id' => $user->id,
                    'suggestion_id' => $suggestion->id,
                    'value' => $value,
                ]);
            } elseif ($existing->value === $value) {
                $existing->delete();
            } else {
                $existing->value = $value;
                $existing->save();
                $vote = $existing;
            }

            $upvotes = SuggestionVote::query()
                ->where('suggestion_id', $suggestion->id)
                ->where('value', 1)
                ->count();
            $downvotes = SuggestionVote::query()
                ->where('suggestion_id', $suggestion->id)
                ->where('value', -1)
                ->count();

            $suggestion->update([
                'upvotes_count' => $upvotes,
                'downvotes_count' => $downvotes,
                'score' => $upvotes - $downvotes,
            ]);

            return $vote;
        });
    }
}
