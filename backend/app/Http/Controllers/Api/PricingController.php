<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público pra landing page mostrar os preços sem precisar de auth.
 * Lê da tabela `plans` (com cache no model). Permite mudar valor via DB
 * sem rebuild/restart.
 */
final class PricingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'free' => ['price_cents' => Plan::priceCentsBySlug('free') ?? 0],
            'pro' => ['price_cents' => Plan::priceCentsBySlug('pro') ?? 2500],
        ]);
    }
}
