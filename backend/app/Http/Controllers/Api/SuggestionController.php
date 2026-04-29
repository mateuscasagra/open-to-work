<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Suggestion\Actions\CastVote;
use App\Domain\Suggestion\Actions\CreateSuggestion;
use App\Domain\Suggestion\DTOs\SuggestionData;
use App\Domain\Suggestion\Exceptions\CannotVoteOwnSuggestionException;
use App\Domain\Suggestion\Exceptions\WeeklyQuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suggestion\CastVoteRequest;
use App\Http\Requests\Suggestion\StoreSuggestionRequest;
use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $topIds = $this->topIds();

        $page = Suggestion::query()
            ->with('user:id,name')
            ->addSelect(['my_vote' => SuggestionVote::query()
                ->select('value')
                ->whereColumn('suggestion_id', 'suggestions.id')
                ->where('user_id', $user->id)
                ->limit(1)])
            ->orderByDesc('score')
            ->orderBy('created_at')
            ->paginate(20);

        $page->getCollection()->transform(
            fn (Suggestion $s): array => $this->serialize(
                $s,
                $topIds,
                $s->getAttribute('my_vote') !== null ? (int) $s->getAttribute('my_vote') : null,
            )
        );

        return response()->json($page);
    }

    public function store(StoreSuggestionRequest $request, CreateSuggestion $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $suggestion = $action->execute($user, SuggestionData::from($request->validated()));
        } catch (WeeklyQuotaExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'next_slot_at' => $e->nextSlotAt->toIso8601String(),
            ], 429);
        }

        $suggestion->load('user:id,name');

        return response()->json(
            $this->serialize($suggestion, $this->topIds(), null),
            201,
        );
    }

    public function destroy(Suggestion $suggestion): JsonResponse
    {
        Gate::authorize('delete', $suggestion);

        $suggestion->delete();

        return response()->json(null, 204);
    }

    public function vote(CastVoteRequest $request, Suggestion $suggestion, CastVote $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $value = $request->validated('value') === 'up' ? 1 : -1;

        try {
            $vote = $action->execute($user, $suggestion, $value);
        } catch (CannotVoteOwnSuggestionException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $suggestion->refresh()->load('user:id,name');

        return response()->json(
            $this->serialize($suggestion, $this->topIds(), $vote?->value),
        );
    }

    public function quota(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = Suggestion::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(CreateSuggestion::WINDOW_DAYS))
            ->orderBy('created_at')
            ->get(['created_at']);

        $count = $rows->count();
        $nextSlotAt = null;
        if ($count >= CreateSuggestion::WEEKLY_LIMIT) {
            $oldest = $rows->first();
            assert($oldest instanceof Suggestion);
            $createdAt = $oldest->created_at;
            assert($createdAt instanceof CarbonInterface);
            $nextSlotAt = $createdAt->copy()->addDays(CreateSuggestion::WINDOW_DAYS)->toIso8601String();
        }

        return response()->json([
            'used' => $count,
            'limit' => CreateSuggestion::WEEKLY_LIMIT,
            'next_slot_at' => $nextSlotAt,
        ]);
    }

    /**
     * @return list<int>
     */
    private function topIds(): array
    {
        return array_values(
            Suggestion::query()
                ->orderByDesc('score')
                ->orderBy('created_at')
                ->limit(3)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
    }

    /**
     * @param  list<int>  $topIds
     * @return array<string, mixed>
     */
    private function serialize(Suggestion $s, array $topIds, ?int $myVote): array
    {
        $position = array_search($s->id, $topIds, true);

        $author = $s->user;
        assert($author instanceof User);
        $createdAt = $s->created_at;
        assert($createdAt instanceof CarbonInterface);

        return [
            'id' => $s->id,
            'user' => [
                'id' => $author->id,
                'name' => $author->name,
            ],
            'title' => $s->title,
            'body' => $s->body,
            'upvotes_count' => $s->upvotes_count,
            'downvotes_count' => $s->downvotes_count,
            'score' => $s->score,
            'my_vote' => $myVote,
            'rank' => $position === false ? null : $position + 1,
            'created_at' => $createdAt->toIso8601String(),
        ];
    }
}
