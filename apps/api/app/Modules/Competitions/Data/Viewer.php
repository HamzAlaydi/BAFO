<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Data;

use App\Modules\Competitions\Enums\ViewerRole;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;

/**
 * The viewer of a competition (ARCHITECTURE §3.6, §8.2), built by
 * `App\Modules\Competitions\Services\ViewerResolver::for(Competition, Actor)`.
 *
 *   issuer       a member of the issuer organization
 *   api_client   an API client of the issuer organization (issuer projection)
 *   participant  its organization joined (`$participant` is set)
 *   invitee      its organization holds an invitation in sent, viewed, declined or expired
 *                (`$invitation` is set)
 *
 * Anyone else gets 404 and never a Viewer.
 */
final readonly class Viewer
{
    public function __construct(
        public ViewerRole $role,
        public int $organizationId,
        public ?Participant $participant = null,
        public ?Invitation $invitation = null,
    ) {}

    /**
     * The issuer side: an issuer member or an issuer API client (both see the issuer projection).
     */
    public function isIssuer(): bool
    {
        return $this->role === ViewerRole::Issuer || $this->role === ViewerRole::ApiClient;
    }

    public function isApiClient(): bool
    {
        return $this->role === ViewerRole::ApiClient;
    }

    public function isParticipant(): bool
    {
        return $this->role === ViewerRole::Participant;
    }

    public function isInvitee(): bool
    {
        return $this->role === ViewerRole::Invitee;
    }
}
