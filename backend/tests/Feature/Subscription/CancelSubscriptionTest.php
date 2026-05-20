<?php

declare(strict_types=1);

use App\Domain\Subscription\Contracts\AsaasGateway;
use App\Models\User;
use Mockery\MockInterface;

it('cancels a Pro subscription and keeps plan=pro until period ends', function (): void {
    $user = $this->makeProUser();
    $periodEnd = $user->subscription->current_period_end;

    $this->mock(AsaasGateway::class, function (MockInterface $m) use ($user): void {
        $m->shouldReceive('cancelSubscription')
            ->once()
            ->with($user->subscription->asaas_subscription_id);
    });

    $this->actingAs($user)
        ->deleteJson('/api/subscriptions')
        ->assertOk()
        ->assertJsonPath('ok', true);

    $user->refresh()->load('subscription');
    expect($user->subscription->status)->toBe('canceled');
    expect($user->subscription->canceled_at)->not->toBeNull();
    // Plan/period intactos — só vai virar free quando scheduler rodar.
    expect($user->subscription->plan)->toBe('pro');
    expect($user->subscription->current_period_end?->equalTo($periodEnd))->toBeTrue();
});

it('returns 422 when free user tries to cancel', function (): void {
    $user = User::factory()->create();

    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        $m->shouldNotReceive('cancelSubscription');
    });

    $this->actingAs($user)->deleteJson('/api/subscriptions')->assertStatus(422);
});

it('requires authentication', function (): void {
    $this->deleteJson('/api/subscriptions')->assertUnauthorized();
});
