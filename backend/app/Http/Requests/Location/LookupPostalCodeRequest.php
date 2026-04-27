<?php

declare(strict_types=1);

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

final class LookupPostalCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:20'],
        ];
    }
}
