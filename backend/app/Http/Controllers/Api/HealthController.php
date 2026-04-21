<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => config('app.version', 'dev'),
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $this->check(fn () => DB::connection()->getPdo()),
                'redis' => $this->check(fn () => Redis::connection()->ping()),
            ],
        ]);
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'up';
        } catch (Throwable $e) {
            return 'down';
        }
    }
}
