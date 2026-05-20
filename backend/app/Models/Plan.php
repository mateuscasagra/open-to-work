<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $slug         'free' | 'pro'
 * @property string $name
 * @property int $price_cents     BRL em centavos
 * @property bool $active
 */
class Plan extends Model
{
    private const CACHE_TTL_SECONDS = 60;

    /** @var list<string> */
    protected $fillable = ['slug', 'name', 'price_cents', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'int',
            'active' => 'boolean',
        ];
    }

    /**
     * Lê o preço (em centavos) de um plano com cache de 60s.
     *
     * Cacheamos **apenas o int**, não o model Eloquent. Cachear o model
     * inteiro é frágil — serialização/desserialização pode dar
     * `__PHP_Incomplete_Class` se autoload/opcache mudar entre puts e gets.
     * Int é seguro e suficiente pro caso de uso (preço pra cobrança).
     */
    public static function priceCentsBySlug(string $slug): ?int
    {
        $cached = Cache::remember(
            "plans.{$slug}.price_cents",
            self::CACHE_TTL_SECONDS,
            static fn (): ?int => static::query()->where('slug', $slug)->value('price_cents'),
        );

        return $cached !== null ? (int) $cached : null;
    }

    /**
     * Busca o plano completo (sem cache). Usar quando precisar de nome/active —
     * pra preço, prefira priceCentsBySlug().
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }

    /**
     * Invalida o cache de preço quando o plano é salvo/deletado. Garante
     * que `UPDATE plans SET price_cents = ...` via Eloquent reflete imediato.
     * SQL direto sem Eloquent respeita o TTL de 60s.
     */
    protected static function booted(): void
    {
        static::saved(function (self $plan): void {
            Cache::forget("plans.{$plan->slug}.price_cents");
        });
        static::deleted(function (self $plan): void {
            Cache::forget("plans.{$plan->slug}.price_cents");
        });
    }
}
