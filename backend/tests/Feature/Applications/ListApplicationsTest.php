<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

it('lists only my applications', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Application::factory()->count(3)->create(['user_id' => $me->id]);
    Application::factory()->count(5)->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->getJson('/api/applications')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters by status', function (): void {
    $user = User::factory()->create();
    Application::factory()->count(2)->inStatus(ApplicationStatus::InterviewTech)->create(['user_id' => $user->id]);
    Application::factory()->count(4)->inStatus(ApplicationStatus::Applied)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/applications?status=interview_tech')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires authentication', function (): void {
    $this->getJson('/api/applications')->assertUnauthorized();
});
