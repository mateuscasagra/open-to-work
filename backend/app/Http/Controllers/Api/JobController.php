<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Job\Queries\ListMatchingJobs;
use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Job::query()
            ->where('active', true)
            ->with(['company', 'sources:id,job_id,external_url'])
            ->latest('posted_at');

        if ($search = $request->string('q')->toString()) {
            $ids = Job::search($search)->keys()->all();
            $query->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        if ($modality = $request->string('modality')->toString()) {
            $query->where('modality', $modality);
        }

        if ($seniority = $request->string('seniority')->toString()) {
            $query->where('seniority', $seniority);
        }

        if ($stack = $request->input('stack')) {
            $stack = is_array($stack) ? $stack : explode(',', (string) $stack);
            foreach ($stack as $tag) {
                $query->whereJsonContains('stack', mb_strtolower(trim((string) $tag)));
            }
        }

        return response()->json($query->paginate(20));
    }

    public function show(Job $job): JsonResponse
    {
        return response()->json($job->load(['company', 'sources']));
    }

    public function matching(Request $request, ListMatchingJobs $query): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json($query->execute($user, $request->integer('per_page', 20)));
    }
}
