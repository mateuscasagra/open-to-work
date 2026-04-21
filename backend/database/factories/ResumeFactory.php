<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resume>
 */
class ResumeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'language' => 'pt_BR',
            'is_pdf_upload' => false,
            'file_path' => null,
            'metadata' => [],
        ];
    }
}
