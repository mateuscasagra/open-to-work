<?php

declare(strict_types=1);

use App\Events\SubscriptionDowngraded;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('downgrades Pro subscription that is canceled and past period_end', function (): void {
    Event::fake([SubscriptionDowngraded::class]);

    $user = $this->makeProUser();
    $user->subscription()->update([
        'status' => 'canceled',
        'canceled_at' => now()->subDays(40),
        'current_period_end' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:downgrade-expired')
        ->expectsOutputToContain('Downgraded 1 subscription')
        ->assertSuccessful();

    $user->refresh()->load('subscription');
    expect($user->subscription->plan)->toBe('free');
    expect($user->subscription->status)->toBe('active');
    expect($user->subscription->asaas_subscription_id)->toBeNull();
    expect($user->subscription->current_period_end)->toBeNull();
    // Customer ID preservado pra reuso em re-assinatura.
    expect($user->subscription->asaas_customer_id)->not->toBeNull();

    Event::assertDispatched(SubscriptionDowngraded::class);
});

it('downgrades Pro subscription that is past_due and past period_end', function (): void {
    $user = $this->makeProUser();
    $user->subscription()->update([
        'status' => 'past_due',
        'current_period_end' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    expect($user->refresh()->subscription->plan)->toBe('free');
});

it('does not downgrade active Pro that is past period_end (paid period intact)', function (): void {
    // Cenário improvável (ativo sem renovação) — mas defesa em profundidade.
    $user = $this->makeProUser();
    $user->subscription()->update([
        'status' => 'active',
        'current_period_end' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    // active não está na whitelist do command — fica intacto.
    expect($user->refresh()->subscription->plan)->toBe('pro');
});

it('does not downgrade canceled Pro that is still within period_end', function (): void {
    $user = $this->makeProUser();
    $user->subscription()->update([
        'status' => 'canceled',
        'canceled_at' => now()->subDays(2),
        'current_period_end' => now()->addDays(10),
    ]);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    // Ainda dentro do período pago — mantém Pro.
    expect($user->refresh()->subscription->plan)->toBe('pro');
    expect($user->refresh()->subscription->status)->toBe('canceled');
});

it('ignores free subscriptions entirely', function (): void {
    User::factory()->count(3)->create();

    $this->artisan('subscriptions:downgrade-expired')
        ->expectsOutputToContain('Downgraded 0 subscription')
        ->assertSuccessful();
});
