<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\PublicV1;

use App\Modules\Competitions\Actions\InviteParticipants;
use App\Modules\Competitions\Actions\RemoveInvitation;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForApiClient;
use App\Modules\Competitions\Http\Requests\StoreInvitationsRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\InvitationPresenter;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Public API invitations (API.md §3.4, flow F3): list, bulk create (all or nothing), delete a
 * draft or revoke a sent / viewed invitation.
 */
final class CompetitionInvitationController extends ApiController
{
    use ActsForApiClient;

    public function __construct(private readonly InvitationPresenter $presenter) {}

    public function index(Competition $competition): JsonResponse
    {
        $this->owned($competition);

        return $this->ok($this->presenter->publicViews(
            Invitation::query()->where('competition_id', $competition->id)->orderBy('id')->get(),
        ));
    }

    public function store(StoreInvitationsRequest $request, Competition $competition, InviteParticipants $invite): JsonResponse
    {
        $this->owned($competition);

        $invitations = $invite->handle($competition, $request->rows($competition->organization_id), $this->actor());

        return $this->created($this->presenter->publicViews($invitations));
    }

    public function destroy(Competition $competition, Invitation $invitation, RemoveInvitation $remove): JsonResponse
    {
        $this->owned($competition);

        $revoked = $remove->handle($invitation, $this->actor());

        return $revoked === null ? $this->noContent() : $this->ok($this->presenter->publicViews(new Collection([$revoked]))[0]);
    }
}
