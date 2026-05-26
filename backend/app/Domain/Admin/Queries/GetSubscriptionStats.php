<?php

declare(strict_types=1);

namespace App\Domain\Admin\Queries;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Agrega métricas de assinaturas para o painel admin.
 *
 * - active: assinaturas Pro ativas E pagas (plan=pro AND status=active)
 * - pending: cobrança gerada no Asaas mas ainda NÃO paga (aguardando 1º pgto)
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
     *     pending: int,
     *     canceled: int,
     *     cancellation_rate: float,
     *     mrr_cents: int,
     *     total_revenue_cents: int
     * }
     */
    public function execute(): array
    {
        // "active" = Pro paga e vigente. Só vira plan='pro' quando o webhook
        // PAYMENT_CONFIRMED chega — então quem gerou cobrança e não pagou NÃO
        // entra aqui. (Era o bug: antes contávamos por status='active' +
        // asaas_subscription_id, inflando o número com cobranças não pagas.)
        $active = (int) DB::table('subscriptions')
            ->where('plan', 'pro')
            ->where('status', 'active')
            ->count();

        // "pending" = cobrança gerada no Asaas aguardando o 1º pagamento
        // (asaas_subscription_id preenchido, mas plan ainda não virou 'pro').
        $pending = (int) DB::table('subscriptions')
            ->whereNotNull('asaas_subscription_id')
            ->where('plan', '!=', 'pro')
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

        // MRR = assinantes pagantes × preço atual do Pro (active já é só pagante).
        $proPrice = Plan::priceCentsBySlug('pro') ?? 2500;
        $mrrCents = $active * $proPrice;

        // Faturamento total: soma do `payment.value` (BRL decimal) de cada webhook
        // PAYMENT_CONFIRMED/RECEIVED processado. Converte pra centavos no SQL.
        $totalRevenueCents = (int) round(((float) DB::table('webhook_logs')
            ->whereIn('event_type', ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'])
            ->whereNotNull('processed_at')
            ->selectRaw("COALESCE(SUM((payload->'payment'->>'value')::numeric), 0) AS total")
            ->value('total')) * 100);

        return [
            'active' => $active,
            'pending' => $pending,
            'canceled' => $canceled,
            'cancellation_rate' => $cancellationRate,
            'mrr_cents' => $mrrCents,
            'total_revenue_cents' => $totalRevenueCents,
        ];
    }
}
