<?php

declare(strict_types=1);

use App\Models\User;

it('reports free plan as not Pro', function (): void {
    $user = User::factory()->create();
    $sub = $user->subscription;

    expect($sub->isPro())->toBeFalse();
    expect($sub->effectivePlan())->toBe('free');
});

it('reports Pro plan as Pro when current_period_end is in the future', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update([
        'plan' => 'pro',
        'status' => 'active',
        'current_period_end' => now()->addDays(10),
    ]);

    $sub = $user->refresh()->subscription;
    expect($sub->isPro())->toBeTrue();
    expect($sub->effectivePlan())->toBe('pro');
});

it('reports Pro plan as not Pro when current_period_end is in the past', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update([
        'plan' => 'pro',
        'status' => 'canceled',
        'current_period_end' => now()->subDay(),
    ]);

    $sub = $user->refresh()->subscription;
    expect($sub->isPro())->toBeFalse();
    expect($sub->effectivePlan())->toBe('free');
});

it('treats Pro with null current_period_end as active (safety net)', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update([
        'plan' => 'pro',
        'status' => 'active',
        'current_period_end' => null,
    ]);

    $sub = $user->refresh()->subscription;
    expect($sub->isPro())->toBeTrue();
});

it('keeps Pro effective while canceled but still within paid period', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update([
        'plan' => 'pro',
        'status' => 'canceled',
        'canceled_at' => now()->subDays(2),
        'current_period_end' => now()->addDays(10),
    ]);

    $sub = $user->refresh()->subscription;
    // Cancelou mas ainda pagou pelo mês — mantém Pro até period_end.
    expect($sub->isPro())->toBeTrue();
    expect($sub->effectivePlan())->toBe('pro');
});
