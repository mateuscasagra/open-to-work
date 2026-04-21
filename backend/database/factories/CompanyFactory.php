<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'domain' => fake()->domainName(),
            'logo_url' => null,
            'linkedin_url' => 'https://linkedin.com/company/' . str()->slug($name),
        ];
    }
}
