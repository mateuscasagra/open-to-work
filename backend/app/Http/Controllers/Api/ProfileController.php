<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile()->with('skills')->firstOrCreate([]);

        return response()->json($profile);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();

        $profile = $request->user()->profile()->firstOrCreate([]);
        $profile->fill($data)->save();

        if (isset($data['skills'])) {
            $profile->skills()->sync($data['skills']);
        }

        return response()->json($profile->load('skills'));
    }
}
