<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\ViewerRole;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;

/**
 * Who sees a competition (ARCHITECTURE §8.2):
 *
 *   issuer       the actor's organization is the issuer (users; API clients → api_client)
 *   participant  a participants row exists for (competition, organization)
 *   invitee      an invitation of the organization in sent, viewed, declined or expired
 *   anyone else  404: existence is never revealed
 *
 * Drafts are visible to the issuer only (their invitations are drafts, so no invitee exists).
 */
final class ViewerResolver
{
    /**
     * Invitation statuses that make an organization an invitee (§8.2), best first: an open
     * invitation wins over a closed one.
     *
     * @var list<InvitationStatus>
     */
    public const array INVITEE_STATUSES = [
        InvitationStatus::Sent,
        InvitationStatus::Viewed,
        InvitationStatus::Declined,
        InvitationStatus::Expired,
    ];

    /**
     * @throws ApiException not_found (404) when the actor has no view of the competition
     */
    public function for(Competition $competition, Actor $actor): Viewer
    {
        return $this->resolve($competition, $actor) ?? throw self::notFound();
    }

    public function resolve(Competition $competition, Actor $actor): ?Viewer
    {
        $organizationId = $actor->organizationId;

        if ($organizationId === null) {
            return null;
        }

        if ($competition->organization_id === $organizationId) {
            return new Viewer($actor->isApiClient() ? ViewerRole::ApiClient : ViewerRole::Issuer, $organizationId);
        }

        // API clients only ever see their own organization's competitions (§8.6).
        if ($actor->isApiClient() || $competition->isDraft()) {
            return null;
        }

        $participant = Participant::query()
            ->where('competition_id', $competition->id)
            ->where('organization_id', $organizationId)
            ->first();

        if ($participant !== null) {
            return new Viewer(ViewerRole::Participant, $organizationId, participant: $participant);
        }

        $invitation = $this->inviteeInvitation($competition, $organizationId);

        return $invitation === null ? null : new Viewer(ViewerRole::Invitee, $organizationId, invitation: $invitation);
    }

    public function inviteeInvitation(Competition $competition, int $organizationId): ?Invitation
    {
        $statuses = array_map(static fn (InvitationStatus $s): string => $s->value, self::INVITEE_STATUSES);

        return Invitation::query()
            ->where('competition_id', $competition->id)
            ->where('organization_id', $organizationId)
            ->whereIn('status', $statuses)
            ->orderByRaw("case status when 'sent' then 0 when 'viewed' then 0 when 'declined' then 1 else 2 end")
            ->orderByDesc('id')
            ->first();
    }

    public static function notFound(): ApiException
    {
        return new ApiException(errorCode: 'not_found', status: 404);
    }
}
