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
        $userId = $request->user()?->id;

        $query = Job::query()
            ->where('active', true)
            ->with(['company', 'sources:id,job_id,source,external_url'])
            ->when($userId !== null, fn ($q) => $q->withExists([
                'applications as has_applied' => fn ($a) => $a->where('user_id', $userId),
            ]))
            ->latest('posted_at');

        if ($search = $request->string('q')->toString()) {
            $ids = Job::search($search)->keys()->all();
            $needle = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($ids, $needle) {
                $q->whereIn('id', $ids !== [] ? $ids : [0])
                    ->orWhereHas('company', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
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

        if ($language = $request->input('language')) {
            $langs = is_array($language) ? $language : explode(',', (string) $language);
            $query->whereIn('language', array_map('trim', $langs));
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
