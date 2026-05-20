<?php

declare(strict_types=1);

namespace App\Domain\Admin\Queries;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Agrega métricas de assinaturas para o painel admin.
 *
 * - active: assinaturas Pro ativas (cobrança em dia)
 * - canceled: Pro com cancel/past_due ainda dentro do período pago
 * - cancellation_rate: canceled / (active + canceled) em %
 * - mrr_cents: MRR estimado = active × preço atual do Pro
 * - total_revenue_cents: soma histórica das cobranças confirmadas (extraída
 *   do payload do webhook PAYMENT_CONFIRMED/RECEIVED salvo em webhook_logs)
 */
final class GetSubscriptionStats
{
    /**
     * @return array{
     *     active: int,
     *     canceled: int,
     *     cancellation_rate: float,
     *     mrr_cents: int,
     *     total_revenue_cents: int
     * }
     */
    public function execute(): array
    {
        // Critério "engajou com pagamento" = asaas_subscription_id preenchido.
        // Não usamos plan='pro' porque só vira `pro` quando webhook
        // PAYMENT_CONFIRMED chega. Quem cancelou ANTES do primeiro pgto fica
        // com (plan=free, status=canceled, asaas_subscription_id preenchido).
        $active = (int) DB::table('subscriptions')
            ->whereNotNull('asaas_subscription_id')
            ->where('status', 'active')
            ->count();

        $canceled = (int) DB::table('subscriptions')
            ->whereNotNull('asaas_subscription_id')
            ->whereIn('status', ['canceled', 'past_due'])
            ->count();

        $totalPaying = $active + $canceled;
        $cancellationRate = $totalPaying > 0
            ? round(($canceled / $totalPaying) * 100, 2)
            : 0.0;

        // MRR conta só quem tem pagamento confirmado (plan=pro AND status=active).
        // active acima pode incluir gente sem primeiro pgto ainda — MRR só conta
        // o que realmente está entrando.
        $payingActive = (int) DB::table('subscriptions')
            ->where('plan', 'pro')
            ->where('status', 'active')
            ->count();
        $proPrice = Plan::priceCentsBySlug('pro') ?? 2500;
        $mrrCents = $payingActive * $proPrice;

        // Faturamento total: soma do `payment.value` (BRL decimal) de cada webhook
        // PAYMENT_CONFIRMED/RECEIVED processado. Converte pra centavos no SQL.
        $totalRevenueCents = (int) round(((float) DB::table('webhook_logs')
            ->whereIn('event_type', ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'])
            ->whereNotNull('processed_at')
            ->selectRaw("COALESCE(SUM((payload->'payment'->>'value')::numeric), 0) AS total")
            ->value('total')) * 100);

        return [
            'active' => $active,
            'canceled' => $canceled,
            'cancellation_rate' => $cancellationRate,
            'mrr_cents' => $mrrCents,
            'total_revenue_cents' => $totalRevenueCents,
        ];
    }
}
