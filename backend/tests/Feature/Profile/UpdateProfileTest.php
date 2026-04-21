<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;

it('updates profile fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'desired_role' => 'Staff Engineer',
            'seniority' => 'senior',
            'modality' => 'remote',
            'salary_min' => 10000,
            'salary_max' => 20000,
            'location' => 'Remote',
            'languages' => ['pt_BR', 'en'],
        ])
        ->assertOk()
        ->assertJsonPath('desired_role', 'Staff Engineer');

    expect(Profile::where('user_id', $user->id)->first()->desired_role)
        ->toBe('Staff Engineer');
});

it('syncs skills by id', function (): void {
    $user = User::factory()->create();
    $skills = Skill::factory()->count(3)->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'skills' => $skills->pluck('id')->toArray(),
        ])
        ->assertOk()
        ->assertJsonCount(3, 'skills');
});

it('rejects salary_max below salary_min', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'salary_min' => 10000,
            'salary_max' => 5000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['salary_max']);
});
