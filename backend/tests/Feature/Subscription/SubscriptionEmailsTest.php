<?php

declare(strict_types=1);

use App\Domain\Subscription\Contracts\AsaasGateway;
use App\Mail\SubscriptionCanceledMail;
use App\Mail\SubscriptionConfirmedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;

beforeEach(function (): void {
    config()->set('services.asaas.webhook_token', 'secret-token');
    Mail::fake();
});

it('queues the confirmation email on first Pro activation', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update(['asaas_subscription_id' => 'sub_xyz']);

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_confirmed_email',
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'pay_1', 'subscription' => 'sub_xyz'],
        ])
        ->assertOk();

    Mail::assertQueued(
        SubscriptionConfirmedMail::class,
        fn (SubscriptionConfirmedMail $mail): bool => $mail->hasTo($user->email),
    );
});

it('does NOT resend the confirmation email on monthly renewal', function (): void {
    // Já é Pro — PAYMENT_RECEIVED de renovação não deve disparar boas-vindas.
    $user = $this->makeProUser();

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_renewal_email',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_renew', 'subscription' => $user->subscription->asaas_subscription_id],
        ])
        ->assertOk();

    Mail::assertNotQueued(SubscriptionConfirmedMail::class);
});

it('queues the cancellation email on SUBSCRIPTION_DELETED webhook', function (): void {
    $user = $this->makeProUser();

    $this->withHeader('asaas-access-token', 'secret-token')
        ->postJson('/api/webhooks/asaas', [
            'id' => 'evt_deleted_email',
            'event' => 'SUBSCRIPTION_DELETED',
            'subscription' => ['id' => $user->subscription->asaas_subscription_id],
        ])
        ->assertOk();

    Mail::assertQueued(
        SubscriptionCanceledMail::class,
        fn (SubscriptionCanceledMail $mail): bool => $mail->hasTo($user->email),
    );
});

it('queues the cancellation email when the user cancels via the API', function (): void {
    $user = $this->makeProUser();

    $this->mock(AsaasGateway::class, function (MockInterface $m) use ($user): void {
        $m->shouldReceive('cancelSubscription')
            ->once()
            ->with($user->subscription->asaas_subscription_id);
    });

    $this->actingAs($user)->deleteJson('/api/subscriptions')->assertOk();

    Mail::assertQueued(SubscriptionCanceledMail::class);
});
