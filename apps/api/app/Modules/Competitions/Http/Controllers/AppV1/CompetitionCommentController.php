<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Actions\PostComment;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Http\Requests\StoreCommentRequest;
use App\Modules\Competitions\Http\Resources\CommentResource;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Support\Http\ApiResponse;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Q&A of a competition (API.md §1.4): issuer members and participants, after publish.
 * Top-level comments newest first, paginated; replies oldest first.
 */
final class CompetitionCommentController extends ApiController
{
    use ActsForCaller;

    public function __construct(private readonly ViewerResolver $viewers) {}

    public function index(Request $request, Competition $competition): JsonResponse
    {
        $this->authorize('discuss', $competition);

        $viewer = $this->viewers->for($competition, $this->actor());

        if ($competition->status === CompetitionStatus::Draft) {
            return ApiResponse::ok([], ['pagination' => ['type' => 'page', 'current_page' => 1, 'per_page' => 20, 'has_more' => false, 'total' => 0, 'last_page' => 1]]);
        }

        $perPage = min(100, max(1, (int) ($request->integer('per_page') ?: 20)));

        $page = Comment::query()
            ->with(['authorOrganization', 'authorParticipant', 'replies' => static fn ($q) => $q
                ->with(['authorOrganization', 'authorParticipant'])
                ->orderBy('created_at')->orderBy('id')])
            ->where('competition_id', $competition->id)
            ->whereNull('parent_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = collect($page->items())
            ->map(static fn (Comment $comment): array => (new CommentResource($comment, $viewer->organizationId, $viewer->isIssuer()))->resolve($request))
            ->values()->all();

        return ApiResponse::ok($data, ['pagination' => ApiResponse::pagination($page)]);
    }

    public function store(StoreCommentRequest $request, Competition $competition, PostComment $post): JsonResponse
    {
        $this->authorize('discuss', $competition);

        $viewer = $this->viewers->for($competition, $this->actor());
        $parent = null;

        if ($request->filled('parent_id')) {
            $parent = Comment::query()
                ->where('competition_id', $competition->id)
                ->whereNull('parent_id')
                ->where('public_id', strtolower($request->string('parent_id')->toString()))
                ->first();

            if ($parent === null) {
                $message = __('competitions.validation.parent_comment');

                throw ValidationException::withMessages(['parent_id' => [is_string($message) ? $message : 'parent_id']]);
            }
        }

        $comment = $post->handle($competition, $viewer, $request->string('body')->toString(), $parent, $this->actor());
        $comment->load(['authorOrganization', 'authorParticipant', 'parent']);

        return $this->created((new CommentResource($comment, $viewer->organizationId, $viewer->isIssuer()))->resolve($request));
    }
}
