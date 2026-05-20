<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Admin\Queries\ListSubscriptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSubscriptionsController extends Controller
{
    public function __construct(private readonly ListSubscriptions $query) {}

    public function __invoke(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString() ?: 'all';
        if (! in_array($status, ['all', 'active', 'canceled'], true)) {
            $status = 'all';
        }

        return response()->json($this->query->execute($status));
    }
}
