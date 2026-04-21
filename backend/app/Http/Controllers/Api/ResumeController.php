<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Resume\Actions\CreateResume;
use App\Domain\Resume\Actions\UpdateResume;
use App\Domain\Resume\DTOs\ResumeData;
use App\Domain\Resume\Queries\ListUserResumes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resume\StoreResumeRequest;
use App\Http\Requests\Resume\UpdateResumeRequest;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ResumeController extends Controller
{
    public function index(Request $request, ListUserResumes $query): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($query->execute($user));
    }

    public function show(Resume $resume): JsonResponse
    {
        Gate::authorize('view', $resume);

        return response()->json($resume->load('sections'));
    }

    public function store(StoreResumeRequest $request, CreateResume $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $resume = $action->execute(
            $user,
            ResumeData::from($request->validated())
        );

        return response()->json($resume, 201);
    }

    public function update(UpdateResumeRequest $request, Resume $resume, UpdateResume $action): JsonResponse
    {
        Gate::authorize('update', $resume);

        $resume = $action->execute($resume, ResumeData::from($request->validated()));

        return response()->json($resume);
    }

    public function destroy(Resume $resume): JsonResponse
    {
        Gate::authorize('delete', $resume);

        $resume->delete();

        return response()->json(null, 204);
    }
}
