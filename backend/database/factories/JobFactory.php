<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Modality;
use App\Enums\Seniority;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'canonical_hash' => hash('sha256', $title . fake()->uuid()),
            'title' => $title,
            'company_id' => Company::factory(),
            'description_html' => '<p>' . fake()->paragraph() . '</p>',
            'location' => fake()->city(),
            'modality' => fake()->randomElement(Modality::cases())->value,
            'seniority' => fake()->randomElement(Seniority::cases())->value,
            'stack' => fake()->randomElements(['php', 'laravel', 'vue', 'typescript', 'postgres', 'redis', 'docker', 'aws'], 3),
            'salary_min' => fake()->numberBetween(3000, 8000),
            'salary_max' => fake()->numberBetween(8000, 25000),
            'salary_currency' => 'BRL',
            'language' => 'pt',
            'posted_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'expires_at' => null,
            'active' => true,
        ];
    }
}
