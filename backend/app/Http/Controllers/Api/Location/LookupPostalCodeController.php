<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Location;

use App\Domain\Location\Actions\LookupPostalCode;
use App\Domain\Location\Exceptions\InvalidPostalCodeException;
use App\Domain\Location\Exceptions\PostalCodeLookupException;
use App\Domain\Location\Exceptions\PostalCodeNotFoundException;
use App\Domain\Location\Exceptions\UnsupportedCountryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\LookupPostalCodeRequest;
use Illuminate\Http\JsonResponse;

final class LookupPostalCodeController extends Controller
{
    public function __construct(private readonly LookupPostalCode $action) {}

    public function __invoke(LookupPostalCodeRequest $request): JsonResponse
    {
        try {
            $result = $this->action->execute(
                $request->string('country_code')->toString(),
                $request->string('postal_code')->toString(),
            );

            return response()->json($result);
        } catch (PostalCodeNotFoundException) {
            return response()->json(['message' => __('location.not_found')], 404);
        } catch (UnsupportedCountryException) {
            return response()->json(['message' => __('location.unsupported_country')], 422);
        } catch (InvalidPostalCodeException) {
            return response()->json(['message' => __('location.invalid_postal_code')], 422);
        } catch (PostalCodeLookupException) {
            return response()->json(['message' => __('location.lookup_failed')], 502);
        }
    }
}
