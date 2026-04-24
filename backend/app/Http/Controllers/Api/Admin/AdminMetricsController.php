<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Admin\Queries\GetAdminMetrics;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class AdminMetricsController extends Controller
{
    public function __construct(private readonly GetAdminMetrics $query) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->query->execute());
    }
}
