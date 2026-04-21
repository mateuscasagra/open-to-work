<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_id' => Job::factory(),
            'resume_id' => null,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'source' => fake()->randomElement(['linkedin', 'indeed', 'direto', 'gupy']),
            'notes' => null,
            'expected_salary' => fake()->optional()->numberBetween(5000, 20000),
        ];
    }

    public function inStatus(ApplicationStatus $status): self
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
