<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Location;

use App\Domain\Location\Queries\ListSupportedCountries;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class SupportedCountriesController extends Controller
{
    public function __construct(private readonly ListSupportedCountries $query) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->query->execute());
    }
}
