<?php

declare(strict_types=1);

namespace App\Domain\Job\Queries;

use App\Models\Job;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista vagas ordenadas por score de compatibilidade com o perfil do usuário.
 *
 * Score = |user.skills ∩ job.stack| + bônus por modality/seniority match.
 * Jobs sem ao menos uma skill em comum ficam de fora.
 */
final class ListMatchingJobs
{
    private const MODALITY_BONUS = 1;
    private const SENIORITY_BONUS = 2;

    public function execute(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $profile = $user->profile()->with('skills')->first();

        $skills = $profile?->skills->pluck('name')->map(
            static fn (string $name): string => mb_strtolower($name)
        )->all() ?? [];

        $profileModality = $profile?->modality?->value;
        $profileSeniority = $profile?->seniority?->value;

        $query = Job::query()
            ->with(['company', 'sources:id,job_id,external_url'])
            ->where('active', true);

        if ($skills === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function (Builder $q) use ($skills): void {
                foreach ($skills as $skill) {
                    $q->orWhereJsonContains('stack', $skill);
                }
            });
        }

        $paginator = $query->latest('posted_at')->paginate($perPage);

        $paginator->getCollection()->transform(
            function (Job $job) use ($skills, $profileModality, $profileSeniority): Job {
                $jobStack = array_map('mb_strtolower', $job->stack ?? []);
                $intersection = array_values(array_intersect($skills, $jobStack));

                $score = count($intersection);
                if ($profileModality !== null && $job->modality?->value === $profileModality) {
                    $score += self::MODALITY_BONUS;
                }
                if ($profileSeniority !== null && $job->seniority?->value === $profileSeniority) {
                    $score += self::SENIORITY_BONUS;
                }

                $job->setAttribute('match_score', $score);
                $job->setAttribute('matched_stack', $intersection);

                return $job;
            }
        );

        $sorted = $paginator->getCollection()->sortByDesc(
            static fn (Job $job): int => (int) $job->getAttribute('match_score')
        )->values();

        $paginator->setCollection($sorted);

        return $paginator;
    }
}
