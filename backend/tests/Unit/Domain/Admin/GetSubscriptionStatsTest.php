<?php

declare(strict_types=1);

use App\Domain\Admin\Queries\GetSubscriptionStats;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function makeSub(array $attrs): void
{
    $user = User::factory()->create();
    $user->subscription()->update($attrs);
}

it('returns zeros when there are no subscriptions with asaas id', function (): void {
    User::factory()->count(3)->create();

    $stats = (new GetSubscriptionStats)->execute();

    expect($stats['active'])->toBe(0)
        ->and($stats['pending'])->toBe(0)
        ->and($stats['canceled'])->toBe(0)
        ->and($stats['cancellation_rate'])->toBe(0.0)
        ->and($stats['mrr_cents'])->toBe(0)
        ->and($stats['total_revenue_cents'])->toBe(0);
});

it('counts only paid Pro as active and unpaid charges as pending', function (): void {
    makeSub(['asaas_subscription_id' => 'sub_a', 'plan' => 'pro', 'status' => 'active']);
    // Cobrança criada mas webhook ainda não confirmou — plan=free. Era o bug:
    // antes isso contava como ativa.
    makeSub(['asaas_subscription_id' => 'sub_b', 'plan' => 'free', 'status' => 'active']);

    $stats = (new GetSubscriptionStats)->execute();

    expect($stats['active'])->toBe(1)
        ->and($stats['pending'])->toBe(1);
});

it('includes past_due in canceled count', function (): void {
    makeSub(['asaas_subscription_id' => 'sub_a', 'status' => 'canceled']);
    makeSub(['asaas_subscription_id' => 'sub_b', 'status' => 'past_due']);

    $stats = (new GetSubscriptionStats)->execute();

    expect($stats['canceled'])->toBe(2);
});

it('calculates cancellation_rate correctly', function (): void {
    makeSub(['asaas_subscription_id' => 'sub_a', 'plan' => 'pro', 'status' => 'active']);
    makeSub(['asaas_subscription_id' => 'sub_b', 'plan' => 'pro', 'status' => 'active']);
    makeSub(['asaas_subscription_id' => 'sub_c', 'plan' => 'pro', 'status' => 'active']);
    makeSub(['asaas_subscription_id' => 'sub_d', 'status' => 'canceled']);

    $stats = (new GetSubscriptionStats)->execute();

    // 1 canceled / 4 total = 25%
    expect($stats['cancellation_rate'])->toBe(25.0);
});

it('active e mrr contam só Pro pago; cobrança não paga vira pending', function (): void {
    makeSub(['asaas_subscription_id' => 'sub_paid', 'plan' => 'pro', 'status' => 'active']);
    makeSub(['asaas_subscription_id' => 'sub_pending', 'plan' => 'free', 'status' => 'active']);

    $stats = (new GetSubscriptionStats)->execute();

    // Preço seedado: 2500.
    expect($stats['active'])->toBe(1);
    expect($stats['pending'])->toBe(1);
    expect($stats['mrr_cents'])->toBe(2500);  // 1 × 2500
});

it('sums total_revenue_cents from webhook_logs payloads', function (): void {
    DB::table('webhook_logs')->insert([
        [
            'event_id' => 'evt_1',
            'source' => 'asaas',
            'event_type' => 'PAYMENT_CONFIRMED',
            'payload' => json_encode(['payment' => ['value' => 25.00]]),
            'processed_at' => now(),
            'created_at' => now(),
        ],
        [
            'event_id' => 'evt_2',
            'source' => 'asaas',
            'event_type' => 'PAYMENT_RECEIVED',
            'payload' => json_encode(['payment' => ['value' => 25.00]]),
            'processed_at' => now(),
            'created_at' => now(),
        ],
        // Não processado — não deve contar
        [
            'event_id' => 'evt_3',
            'source' => 'asaas',
            'event_type' => 'PAYMENT_CONFIRMED',
            'payload' => json_encode(['payment' => ['value' => 999.00]]),
            'processed_at' => null,
            'created_at' => now(),
        ],
        // Outro tipo — não deve contar
        [
            'event_id' => 'evt_4',
            'source' => 'asaas',
            'event_type' => 'SUBSCRIPTION_DELETED',
            'payload' => json_encode(['payment' => ['value' => 50.00]]),
            'processed_at' => now(),
            'created_at' => now(),
        ],
    ]);

    $stats = (new GetSubscriptionStats)->execute();

    // 25 + 25 = R$ 50,00 = 5000 cents
    expect($stats['total_revenue_cents'])->toBe(5000);
});
