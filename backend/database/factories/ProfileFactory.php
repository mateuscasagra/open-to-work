<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Modality;
use App\Enums\Seniority;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'desired_role' => fake()->jobTitle(),
            'seniority' => fake()->randomElement(Seniority::cases())->value,
            'modality' => fake()->randomElement(Modality::cases())->value,
            'salary_min' => fake()->numberBetween(3000, 8000),
            'salary_max' => fake()->numberBetween(8000, 25000),
            'salary_currency' => 'BRL',
            'location' => fake()->city(),
            'languages' => ['pt_BR', 'en'],
            'bio' => fake()->paragraph(),
        ];
    }
}
