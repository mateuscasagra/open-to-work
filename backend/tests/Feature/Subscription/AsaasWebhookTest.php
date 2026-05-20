<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('services.asaas.webhook_token', 'secret-token');
});

it('returns 401 when header asaas-access-token is missing or wrong', function (): void {
    $this->postJson('/api/webhooks/asaas', ['id' => 'evt_1', 'event' => 'PAYMENT_CONFIRMED'])
        ->assertStatus(401);

    $this->withHeader('asaas-access-token', 'wrong')
        ->postJson('/api/webhooks/asaas', ['id' => 'evt_1', 'event' => 'PAYMENT_CONFIRMED'])
        ->assertStatus(401);
});

it('returns 400 when payload has no event id', function (): void {
    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', ['event' => 'PAYMENT_CONFIRMED'])
        ->assertStatus(400);
});

it('returns 400 when payload has no event type', function (): void {
    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', ['id' => 'evt_1'])
        ->assertStatus(400);
});

it('activates Pro on PAYMENT_CONFIRMED webhook', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update(['asaas_subscription_id' => 'sub_xyz']);

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_confirmed_1',
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'pay_1', 'subscription' => 'sub_xyz'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    $user->refresh()->load('subscription');
    expect($user->subscription->plan)->toBe('pro');
    expect($user->subscription->status)->toBe('active');
    expect($user->subscription->current_period_end)->not->toBeNull();
    expect($user->subscription->current_period_end->isFuture())->toBeTrue();
    expect($user->subscription->last_payment_id)->toBe('pay_1');
});

it('activates Pro on PAYMENT_RECEIVED webhook (same handler as confirmed)', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update(['asaas_subscription_id' => 'sub_xyz']);

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_received_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_2', 'subscription' => 'sub_xyz'],
        ])
        ->assertOk();

    expect($user->refresh()->subscription->plan)->toBe('pro');
});

it('marks subscription as past_due on PAYMENT_OVERDUE without touching plan', function (): void {
    $user = $this->makeProUser();
    $periodEnd = $user->subscription->current_period_end;

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_overdue_1',
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['id' => 'pay_3', 'subscription' => $user->subscription->asaas_subscription_id],
        ])
        ->assertOk();

    $user->refresh()->load('subscription');
    expect($user->subscription->status)->toBe('past_due');
    expect($user->subscription->plan)->toBe('pro');
    expect($user->subscription->current_period_end?->equalTo($periodEnd))->toBeTrue();
});

it('marks subscription canceled on SUBSCRIPTION_DELETED without touching plan/period', function (): void {
    $user = $this->makeProUser();
    $periodEnd = $user->subscription->current_period_end;

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_deleted_1',
            'event' => 'SUBSCRIPTION_DELETED',
            'subscription' => ['id' => $user->subscription->asaas_subscription_id],
        ])
        ->assertOk();

    $user->refresh()->load('subscription');
    expect($user->subscription->status)->toBe('canceled');
    expect($user->subscription->canceled_at)->not->toBeNull();
    expect($user->subscription->plan)->toBe('pro');
    expect($user->subscription->current_period_end?->equalTo($periodEnd))->toBeTrue();
});

it('is idempotent on replay of the same event id', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update(['asaas_subscription_id' => 'sub_xyz']);

    $payload = [
        'id' => 'evt_replay_1',
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'pay_1', 'subscription' => 'sub_xyz'],
    ];

    // Primeira chamada processa.
    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    // Segunda chamada com mesmo id → duplicate, sem reprocessar.
    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'duplicate');

    // webhook_logs deve ter apenas 1 entrada pra esse event_id.
    expect(DB::table('webhook_logs')->where('event_id', 'evt_replay_1')->count())->toBe(1);
});

it('accepts unknown events with 200 and marks processed_at (no-op)', function (): void {
    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_unknown_1',
            'event' => 'TRANSFER_CREATED',
            'transfer' => ['id' => 't_1'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    $row = DB::table('webhook_logs')->where('event_id', 'evt_unknown_1')->first();
    expect($row)->not->toBeNull();
    expect($row->processed_at)->not->toBeNull();
});
