<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;

/**
 * Payload mínimo válido — incluindo os campos geográficos exigidos pelo
 * UpdateProfileRequest. País sem lookup pra dispensar postal_code.
 */
function validLocationPayload(array $overrides = []): array
{
    return array_merge([
        'country_code' => 'AR',
        'state_name' => 'Buenos Aires',
        'city' => 'Buenos Aires',
    ], $overrides);
}

it('updates profile fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', validLocationPayload([
            'desired_role' => 'Staff Engineer',
            'seniority' => 'senior',
            'modality' => 'remote',
            'salary_min' => 10000,
            'salary_max' => 20000,
            'languages' => ['pt_BR', 'en'],
        ]))
        ->assertOk()
        ->assertJsonPath('desired_role', 'Staff Engineer');

    expect(Profile::where('user_id', $user->id)->first()->desired_role)
        ->toBe('Staff Engineer');
});

it('persists structured location fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', validLocationPayload([
            'country_code' => 'BR',
            'postal_code' => '01310100',
            'state_code' => 'SP',
            'state_name' => 'São Paulo',
            'city' => 'São Paulo',
        ]))
        ->assertOk()
        ->assertJsonPath('country_code', 'BR')
        ->assertJsonPath('state_code', 'SP')
        ->assertJsonPath('city', 'São Paulo');
});

it('syncs skills by id', function (): void {
    $user = User::factory()->create();
    $skills = Skill::factory()->count(3)->create();

    $this->actingAs($user)
        ->putJson('/api/profile', validLocationPayload([
            'skills' => $skills->pluck('id')->toArray(),
        ]))
        ->assertOk()
        ->assertJsonCount(3, 'skills');
});

it('rejects salary_max below salary_min', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', validLocationPayload([
            'salary_min' => 10000,
            'salary_max' => 5000,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['salary_max']);
});

it('rejects unknown country_code', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'country_code' => 'ZZ',
            'state_name' => 'Foo',
            'city' => 'Bar',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country_code']);
});

it('requires country_code, state_name and city', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'desired_role' => 'Backend',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country_code', 'state_name', 'city']);
});

it('requires postal_code when country supports lookup', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'country_code' => 'BR',
            'state_name' => 'São Paulo',
            'city' => 'São Paulo',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['postal_code']);
});

it('does not require postal_code when country has no lookup', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', validLocationPayload())
        ->assertOk();
});
