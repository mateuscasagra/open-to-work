<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Application\Actions\ChangeApplicationStatus;
use App\Domain\Application\Actions\CreateApplication;
use App\Domain\Application\DTOs\ApplicationData;
use App\Domain\Application\Exceptions\DuplicateApplicationException;
use App\Domain\Subscription\Exceptions\QuotaExceededException;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Application\ChangeStatusRequest;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Requests\Application\UpdateApplicationRequest;
use App\Models\Application;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->applications()->with(['job.company', 'resume']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        return response()->json($query->latest('applied_at')->paginate(20));
    }

    public function store(StoreApplicationRequest $request, CreateApplication $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $application = $action->execute(
                $user,
                ApplicationData::from($request->validated())
            );
        } catch (DuplicateApplicationException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (QuotaExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'kind' => 'quota_exceeded',
                'used' => $e->used,
                'limit' => $e->limit,
                'plan' => $e->plan,
                'reset_at' => $e->resetAt,
            ], 402);
        }

        return response()->json($application->load(['job.company']), 201);
    }

    public function show(Application $application): JsonResponse
    {
        Gate::authorize('view', $application);

        return response()->json($application->load(['job.company', 'resume', 'events']));
    }

    public function destroy(Application $application): JsonResponse
    {
        Gate::authorize('delete', $application);

        $application->delete();

        return response()->json(null, 204);
    }

    public function changeStatus(
        ChangeStatusRequest $request,
        Application $application,
        ChangeApplicationStatus $action,
    ): JsonResponse {
        Gate::authorize('update', $application);

        try {
            $application = $action->execute(
                $application,
                ApplicationStatus::from($request->validated('status')),
                $request->validated('note'),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($application);
    }

    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        Gate::authorize('update', $application);

        $application->update($request->validated());

        return response()->json($application->load(['job.company', 'resume']));
    }

    public function archive(Application $application): JsonResponse
    {
        Gate::authorize('update', $application);

        if ($application->archived_at === null) {
            $application->update(['archived_at' => now()]);
            $application->events()->create([
                'event_type' => 'archived',
                'payload' => null,
                'occurred_at' => now(),
            ]);
        }

        return response()->json($application->load(['job.company', 'resume']));
    }

    public function unarchive(Application $application): JsonResponse
    {
        Gate::authorize('update', $application);

        if ($application->archived_at !== null) {
            $application->update(['archived_at' => null]);
            $application->events()->create([
                'event_type' => 'unarchived',
                'payload' => null,
                'occurred_at' => now(),
            ]);
        }

        return response()->json($application->load(['job.company', 'resume']));
    }
}
