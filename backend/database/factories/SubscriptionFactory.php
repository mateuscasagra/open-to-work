<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => 'free',
            'status' => 'active',
            'asaas_customer_id' => null,
            'asaas_subscription_id' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'canceled_at' => null,
            'last_payment_at' => null,
            'last_payment_id' => null,
        ];
    }

    /** Pro ativo: dentro do período pago. */
    public function pro(): static
    {
        return $this->state(fn () => [
            'plan' => 'pro',
            'status' => 'active',
            'asaas_customer_id' => 'cus_test_' . fake()->bothify('##########'),
            'asaas_subscription_id' => 'sub_test_' . fake()->bothify('##########'),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'last_payment_at' => now(),
            'last_payment_id' => 'pay_test_' . fake()->bothify('##########'),
        ]);
    }

    /** Pro com cancelamento agendado: ainda dentro do período pago. */
    public function proCanceled(): static
    {
        return $this->pro()->state(fn () => [
            'status' => 'canceled',
            'canceled_at' => now()->subDays(2),
            'current_period_end' => now()->addDays(10),
        ]);
    }

    /** Pro vencido: alvo do command DowngradeExpiredSubscriptions. */
    public function proExpired(): static
    {
        return $this->pro()->state(fn () => [
            'status' => 'canceled',
            'canceled_at' => now()->subDays(40),
            'current_period_end' => now()->subDay(),
        ]);
    }
}
