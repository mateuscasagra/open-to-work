<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Metrics\Queries\GetUserMetrics;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MetricsController extends Controller
{
    public function __construct(private readonly GetUserMetrics $query) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $fromDate = $from !== '' ? CarbonImmutable::parse($from) : null;
        $toDate = $to !== '' ? CarbonImmutable::parse($to) : null;

        $summary = $this->query->execute($user, $fromDate, $toDate);

        return response()->json($summary);
    }
}
