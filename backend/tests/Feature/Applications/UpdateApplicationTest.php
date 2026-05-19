<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;

it('updates notes and expected salary on my application', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'notes' => null,
        'expected_salary' => null,
        'job_url' => null,
        'manual_title' => null,
    ]);

    $response = $this->actingAs($user)
        ->putJson("/api/applications/{$application->id}", [
            'notes' => 'Entrevistador: João. Próxima etapa em 3 dias.',
            'expected_salary' => 14000,
            'job_url' => 'https://empresa.com/vagas/42',
            'manual_title' => 'Backend Sênior PHP/Laravel',
        ]);

    $response->assertOk()
        ->assertJsonPath('notes', 'Entrevistador: João. Próxima etapa em 3 dias.')
        ->assertJsonPath('expected_salary', 14000)
        ->assertJsonPath('job_url', 'https://empresa.com/vagas/42')
        ->assertJsonPath('manual_title', 'Backend Sênior PHP/Laravel');

    $application->refresh();
    expect($application->notes)->toBe('Entrevistador: João. Próxima etapa em 3 dias.');
    expect($application->job_url)->toBe('https://empresa.com/vagas/42');
    expect($application->manual_title)->toBe('Backend Sênior PHP/Laravel');
});

it('ignores unauthorized fields', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'applied',
    ]);

    // Attempting to change user_id + status via update should be ignored (only notes/expected_salary allowed).
    $this->actingAs($user)
        ->putJson("/api/applications/{$application->id}", [
            'user_id' => $other->id,
            'status' => 'offer',
            'notes' => 'legit note',
        ])
        ->assertOk();

    $fresh = $application->fresh();
    expect($fresh->user_id)->toBe($user->id);
    expect($fresh->status->value)->toBe('applied');
    expect($fresh->notes)->toBe('legit note');
});

it('blocks updating another user application', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherApp = Application::factory()->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->putJson("/api/applications/{$otherApp->id}", ['notes' => 'hack'])
        ->assertForbidden();
});
