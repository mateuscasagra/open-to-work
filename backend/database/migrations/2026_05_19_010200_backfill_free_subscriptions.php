<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cria uma subscription 'free' / 'active' pra todo user que ainda não tem.
        // ON CONFLICT (user_id) é seguro porque subscriptions.user_id é UNIQUE.
        DB::statement(<<<'SQL'
            INSERT INTO subscriptions (user_id, plan, status, created_at, updated_at)
            SELECT id, 'free', 'active', NOW(), NOW()
            FROM users
            ON CONFLICT (user_id) DO NOTHING
        SQL);
    }

    public function down(): void
    {
        // Rollback: limpa apenas subs que esta migration teria criado (free/active sem asaas_*).
        // Não toca em assinaturas Pro reais.
        DB::table('subscriptions')
            ->where('plan', 'free')
            ->where('status', 'active')
            ->whereNull('asaas_customer_id')
            ->whereNull('asaas_subscription_id')
            ->delete();
    }
};
