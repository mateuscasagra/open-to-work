<?php

declare(strict_types=1);

use App\Domain\Subscription\DTOs\QuotaData;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;

it('allows a free user with 14 applications this month to create the 15th', function (): void {
    $user = User::factory()->create();

    Application::factory()->count(14)->create([
        'user_id' => $user->id,
        'applied_at' => now(),
    ]);

    $job = Job::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/applications', ['jobId' => $job->id])
        ->assertCreated();

    expect($user->applications()->count())->toBe(15);
});

it('blocks a free user with 15 applications this month with 402 and quota_exceeded body', function (): void {
    $user = User::factory()->create();

    Application::factory()->count(QuotaData::FREE_MONTHLY_LIMIT)->create([
        'user_id' => $user->id,
        'applied_at' => now(),
    ]);

    $job = Job::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/applications', ['jobId' => $job->id])
        ->assertStatus(402);

    $response->assertJsonPath('kind', 'quota_exceeded')
        ->assertJsonPath('used', QuotaData::FREE_MONTHLY_LIMIT)
        ->assertJsonPath('limit', QuotaData::FREE_MONTHLY_LIMIT)
        ->assertJsonPath('plan', 'free')
        ->assertJsonStructure(['message', 'kind', 'used', 'limit', 'plan', 'reset_at']);

    // Não criou — count permanece em 15.
    expect($user->applications()->count())->toBe(QuotaData::FREE_MONTHLY_LIMIT);
});

it('allows Pro user to create unlimited applications', function (): void {
    $user = $this->makeProUser();

    Application::factory()->count(50)->create([
        'user_id' => $user->id,
        'applied_at' => now(),
    ]);

    $job = Job::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/applications', ['jobId' => $job->id])
        ->assertCreated();

    expect($user->applications()->count())->toBe(51);
});

it('does not count applications from previous months toward this month quota', function (): void {
    $user = User::factory()->create();

    // 20 candidaturas do mês passado — não devem contar.
    Application::factory()->count(20)->create([
        'user_id' => $user->id,
        'applied_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
    ]);

    $job = Job::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/applications', ['jobId' => $job->id])
        ->assertCreated();
});
