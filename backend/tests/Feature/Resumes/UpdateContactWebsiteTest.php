<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;

it('persists website field when updating contact section', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);
    $resume->sections()->create([
        'type' => 'contact',
        'order' => 0,
        'content' => ['email' => 'a@a.com', 'website' => 'old.com'],
    ]);

    $this->actingAs($user)
        ->putJson("/api/resumes/{$resume->id}", [
            'title' => $resume->title,
            'language' => 'pt_BR',
            'sections' => [
                [
                    'type' => 'contact',
                    'order' => 0,
                    'content' => ['email' => 'a@a.com', 'website' => 'NEW-VALUE.com'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('sections.0.content.website', 'NEW-VALUE.com');

    $reloaded = Resume::find($resume->id);
    expect($reloaded->sections->first()->content['website'])->toBe('NEW-VALUE.com');
});
