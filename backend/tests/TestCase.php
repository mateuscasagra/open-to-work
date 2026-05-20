<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $statefulDomain = explode(',', (string) config('sanctum.stateful')[0] ?? 'localhost')[0];
        $this->withHeader('Referer', 'http://' . $statefulDomain);
    }

    /**
     * Promove o user pra Pro (atualizando a subscription que o User::booted()
     * já criou como free). Use em testes que precisam de Pro pra não bater
     * quota / acessar features exclusivas.
     */
    protected function makeProUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $user->subscription()->update([
            'plan' => 'pro',
            'status' => 'active',
            'asaas_customer_id' => 'cus_test_' . $user->id,
            'asaas_subscription_id' => 'sub_test_' . $user->id,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'last_payment_at' => now(),
            'last_payment_id' => 'pay_test_' . $user->id,
        ]);

        return $user->refresh()->load('subscription');
    }
}
