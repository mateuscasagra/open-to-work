<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;

it('updates title and replaces sections when provided', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id, 'title' => 'Antigo']);
    $resume->sections()->create(['type' => 'summary', 'order' => 0, 'content' => ['text' => 'old']]);
    $resume->sections()->create(['type' => 'experience', 'order' => 1, 'content' => ['company' => 'X']]);

    $this->actingAs($user)
        ->putJson("/api/resumes/{$resume->id}", [
            'title' => 'Novo',
            'language' => 'en',
            'sections' => [
                ['type' => 'summary', 'order' => 0, 'content' => ['text' => 'new']],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('title', 'Novo')
        ->assertJsonPath('language', 'en')
        ->assertJsonCount(1, 'sections')
        ->assertJsonPath('sections.0.content.text', 'new');

    expect($resume->fresh()->sections()->count())->toBe(1);
});

it('updates title without touching sections when sections are absent', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);
    $resume->sections()->create(['type' => 'summary', 'order' => 0, 'content' => ['text' => 'keep']]);

    $this->actingAs($user)
        ->putJson("/api/resumes/{$resume->id}", ['title' => 'Só título'])
        ->assertOk()
        ->assertJsonPath('title', 'Só título')
        ->assertJsonCount(1, 'sections');

    expect($resume->fresh()->sections()->first()->content['text'])->toBe('keep');
});

it('forbids updating another user resume', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->putJson("/api/resumes/{$resume->id}", ['title' => 'Hack'])
        ->assertForbidden();
});
