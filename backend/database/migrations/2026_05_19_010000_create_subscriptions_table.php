<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 10)->default('free');     // 'free' | 'pro'
            $table->string('status', 20)->default('active'); // 'active' | 'canceled' | 'past_due'
            $table->string('asaas_customer_id', 50)->nullable();
            $table->string('asaas_subscription_id', 50)->nullable()->unique();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->string('last_payment_id', 50)->nullable();
            $table->timestamps();

            $table->index('asaas_customer_id', 'subscriptions_customer_idx');
            $table->index('current_period_end', 'subscriptions_period_end_idx');
            $table->index(['plan', 'status'], 'subscriptions_plan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
