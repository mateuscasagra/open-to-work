<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\ErrorLog\Queries\ListErrorLogs;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminErrorLogsController extends Controller
{
    public function __construct(private readonly ListErrorLogs $query) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', ListErrorLogs::DEFAULT_LIMIT);
        $level = $request->string('level')->toString() ?: null;

        return response()->json($this->query->execute($limit, $level)->toArray());
    }
}
