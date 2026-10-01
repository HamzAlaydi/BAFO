<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Actions\CreateCompetition;
use App\Modules\Competitions\Actions\DeleteDraftCompetition;
use App\Modules\Competitions\Actions\MarkInvitationViewed;
use App\Modules\Competitions\Actions\UpdateCompetition;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Http\Requests\ListCompetitionsRequest;
use App\Modules\Competitions\Http\Requests\StoreCompetitionRequest;
use App\Modules\Competitions\Http\Requests\UpdateCompetitionRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionListPresenter;
use App\Modules\Competitions\Services\CompetitionPresenter;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Support\Auth\CurrentActor;
use App\Support\Http\ApiResponse;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Competitions CRUD (API.md §1.4): list by role, create a draft, show projected for the viewer,
 * update by status, delete a draft.
 */
final class CompetitionController extends ApiController
{
    use ActsForCaller;

    public function __construct(
        private readonly ViewerResolver $viewers,
        private readonly CompetitionPresenter $presenter,
    ) {}

    public function index(ListCompetitionsRequest $request, CompetitionListPresenter $list): JsonResponse
    {
        $organization = $this->organization();
        $statuses = $request->statuses() !== [] ? $request->statuses() : $request->statusGroup();
        $issuer = $request->input('role') === 'issuer';

        $query = Competition::query()
            ->with(['category', 'region', 'organization.logoFile'])
            ->when($issuer, static fn (Builder $q) => $q
                ->where('organization_id', $organization->id)
                ->withCount([
                    'invitations' => static fn ($i) => $i->where('status', '!=', InvitationStatus::Revoked->value),
                    'invitations as joined_count' => static fn ($i) => $i->where('status', InvitationStatus::Joined->value),
                ]))
            ->when(! $issuer, static fn (Builder $q) => $q
                ->where('status', '!=', 'draft')
                ->whereHas('invitations', static fn ($i) => $i
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', CompetitionListPresenter::participantStatuses())))
            ->when($statuses !== [], static fn (Builder $q) => $q->whereIn('status', $statuses))
            ->when($request->filled('direction'), static fn (Builder $q) => $q->where('direction', $request->input('direction')))
            ->when($request->filled('q'), static fn (Builder $q) => $q->where('title', 'ilike', '%'.addcslashes(trim($request->string('q')->toString()), '%_\\').'%'));

        match ($request->input('sort', '-updated_at')) {
            'effective_close_at' => $query->orderByRaw('effective_close_at asc nulls last'),
            '-created_at' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('updated_at'),
        };

        $page = $query->orderByDesc('id')->paginate($request->perPage());

        /** @var Collection<int, Competition> $items */
        $items = collect($page->items());
        $data = $issuer ? $list->issuerItems($items) : $list->participantItems($items, $organization);

        return ApiResponse::ok($data, ['pagination' => ApiResponse::pagination($page)]);
    }

    public function store(StoreCompetitionRequest $request, CreateCompetition $create): JsonResponse
    {
        $this->authorize('create', Competition::class);

        $actor = CurrentActor::get();
        $competition = $create->handle($this->organization(), $request->toInput(), $actor);

        return $this->created($this->presenter->present($competition, $this->viewers->for($competition, $actor), $this->user($request)));
    }

    public function show(Request $request, Competition $competition, MarkInvitationViewed $markViewed): JsonResponse
    {
        $this->authorize('view', $competition);

        $actor = CurrentActor::get();
        $viewer = $this->viewers->for($competition, $actor);

        // An invitee's first view sets the invitation to viewed (API.md §1.4).
        if ($viewer->isInvitee() && $viewer->invitation !== null && $viewer->invitation->status === InvitationStatus::Sent) {
            $markViewed->handle($viewer->invitation, $actor);
            $viewer = $this->viewers->for($competition, $actor);
        }

        return $this->ok($this->presenter->present($competition, $viewer, $this->user($request)));
    }

    public function update(UpdateCompetitionRequest $request, Competition $competition, UpdateCompetition $update): JsonResponse
    {
        $this->authorize('manage', $competition);

        $actor = CurrentActor::get();
        $competition = $update->handle($competition, $request->toInput(), $actor);

        return $this->ok($this->presenter->present($competition, $this->viewers->for($competition, $actor), $this->user($request)));
    }

    public function destroy(Competition $competition, DeleteDraftCompetition $delete): JsonResponse
    {
        $this->authorize('manage', $competition);

        $delete->handle($competition, CurrentActor::get());

        return $this->noContent();
    }
}
