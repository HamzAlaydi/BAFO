<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Actions\InviteParticipants;
use App\Modules\Competitions\Actions\RemoveInvitation;
use App\Modules\Competitions\Actions\ResendInvitation;
use App\Modules\Competitions\Actions\UpdateInvitation;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Http\Requests\StoreInvitationsRequest;
use App\Modules\Competitions\Http\Requests\UpdateInvitationRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\InvitationPresenter;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The issuer's invitations of a competition (API.md §1.4).
 */
final class CompetitionInvitationController extends ApiController
{
    use ActsForCaller;

    public function __construct(private readonly InvitationPresenter $presenter) {}

    public function index(Request $request, Competition $competition): JsonResponse
    {
        $this->authorize('viewAsIssuer', $competition);

        $all = Invitation::query()->where('competition_id', $competition->id)->orderBy('id')->get();
        $statuses = array_values(array_filter(array_map('trim', explode(',', $request->string('status')->toString()))));
        $listed = $statuses === [] ? $all : $all->filter(static fn (Invitation $i): bool => in_array($i->status->value, $statuses, true))->values();

        $counts = array_fill_keys(array_map(static fn (InvitationStatus $s): string => $s->value, InvitationStatus::cases()), 0);

        foreach ($all as $invitation) {
            $counts[$invitation->status->value]++;
        }

        return $this->ok($this->presenter->issuerViews($listed), ['counts' => $counts]);
    }

    public function store(StoreInvitationsRequest $request, Competition $competition, InviteParticipants $invite): JsonResponse
    {
        $this->authorize('manage', $competition);

        $invitations = $invite->handle($competition, $request->rows($competition->organization_id), $this->actor());

        return $this->created($this->presenter->issuerViews($invitations));
    }

    public function update(UpdateInvitationRequest $request, Competition $competition, Invitation $invitation, UpdateInvitation $update): JsonResponse
    {
        $this->authorize('manage', $competition);

        return $this->ok($this->presenter->issuerViewOf($update->handle($invitation, $request->changes(), $this->actor())));
    }

    public function destroy(Competition $competition, Invitation $invitation, RemoveInvitation $remove): JsonResponse
    {
        $this->authorize('manage', $competition);

        $revoked = $remove->handle($invitation, $this->actor());

        return $revoked === null ? $this->noContent() : $this->ok($this->presenter->issuerViewOf($revoked));
    }

    public function resend(Competition $competition, Invitation $invitation, ResendInvitation $resend): JsonResponse
    {
        $this->authorize('manage', $competition);

        $resend->handle($invitation, $this->actor());

        return $this->noContent();
    }
}
