<?php

declare(strict_types=1);

namespace App\Domain\Admin\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Listagem paginada de assinaturas pro painel admin. Filtros:
 * - 'active'   → status=active
 * - 'canceled' → status IN (canceled, past_due)
 * - 'all'      → qualquer sub com asaas_subscription_id preenchido
 *
 * Critério base: `asaas_subscription_id IS NOT NULL` (engajou com pagamento).
 * Não usa `plan='pro'` porque o plano só vira pro quando webhook confirma;
 * quem cancela antes do primeiro pagamento ficaria fora indevidamente.
 */
final class ListSubscriptions
{
    public const DEFAULT_PER_PAGE = 20;

    /**
     * @param  'active'|'canceled'|'all'  $status
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function execute(string $status = 'all', int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $query = DB::table('subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->whereNotNull('subscriptions.asaas_subscription_id')
            ->select(
                'subscriptions.id',
                'subscriptions.user_id',
                'users.name',
                'users.email',
                'subscriptions.plan',
                'subscriptions.status',
                'subscriptions.asaas_subscription_id',
                'subscriptions.current_period_start',
                'subscriptions.current_period_end',
                'subscriptions.canceled_at',
                'subscriptions.last_payment_at',
                'subscriptions.created_at',
            )
            ->orderByDesc('subscriptions.created_at');

        if ($status === 'active') {
            $query->where('subscriptions.status', 'active');
        } elseif ($status === 'canceled') {
            $query->whereIn('subscriptions.status', ['canceled', 'past_due']);
        }
        // 'all' não aplica filtro extra além do whereNotNull.

        return $query->paginate($perPage);
    }
}
