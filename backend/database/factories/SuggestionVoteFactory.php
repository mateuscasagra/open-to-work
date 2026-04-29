<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestionVote>
 */
class SuggestionVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'suggestion_id' => Suggestion::factory(),
            'value' => 1,
        ];
    }

    public function down(): self
    {
        return $this->state(fn (): array => ['value' => -1]);
    }
}
