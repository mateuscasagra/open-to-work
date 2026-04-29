<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(6),
            'body' => fake()->paragraph(3),
            'upvotes_count' => 0,
            'downvotes_count' => 0,
            'score' => 0,
        ];
    }
}
