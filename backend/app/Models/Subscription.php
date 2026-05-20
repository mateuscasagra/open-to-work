<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $plan 'free' | 'pro'
 * @property string $status 'active' | 'canceled' | 'past_due'
 * @property ?string $asaas_customer_id
 * @property ?string $asaas_subscription_id
 * @property ?string $cpf
 * @property ?Carbon $current_period_start
 * @property ?Carbon $current_period_end
 * @property ?Carbon $canceled_at
 * @property ?Carbon $last_payment_at
 * @property ?string $last_payment_id
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'asaas_customer_id',
        'asaas_subscription_id',
        'cpf',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'last_payment_at',
        'last_payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'last_payment_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * O usuário tem acesso a recursos Pro agora?
     *
     * Considera Pro quando plan='pro' E (não há fim de período OU fim de período no futuro).
     * Status canceled/past_due ainda dá acesso até o período acabar — é o ciclo já pago.
     */
    public function isPro(): bool
    {
        if ($this->plan !== 'pro') {
            return false;
        }

        if ($this->current_period_end === null) {
            return true;
        }

        return $this->current_period_end->isFuture();
    }

    /**
     * Retorna o plano efetivo (defesa em profundidade caso o scheduler de
     * downgrade atrase): se DB diz 'pro' mas período venceu, trata como 'free'.
     */
    public function effectivePlan(): string
    {
        return $this->isPro() ? 'pro' : 'free';
    }
}
