<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\ResumeSection;
use App\Models\User;

it('deletes a resume and its sections', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);
    $resume->sections()->create(['type' => 'summary', 'order' => 0, 'content' => ['text' => 'x']]);

    $this->actingAs($user)
        ->deleteJson("/api/resumes/{$resume->id}")
        ->assertNoContent();

    expect(Resume::count())->toBe(0);
    expect(ResumeSection::count())->toBe(0);
});

it('forbids deleting another user resume', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->deleteJson("/api/resumes/{$resume->id}")
        ->assertForbidden();

    expect(Resume::count())->toBe(1);
});
