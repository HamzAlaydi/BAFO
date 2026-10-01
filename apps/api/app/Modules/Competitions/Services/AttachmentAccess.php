<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\AccessState;
use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Files\File;

/**
 * The `competition_attachment` file rule (ARCHITECTURE §8.5), registered with
 * FileAccessRegistry: the issuer; joined participants; invitees whose access state is not
 * `unavailable`, for `invitation_document` only.
 */
final readonly class AttachmentAccess
{
    public function __construct(
        private ViewerResolver $viewers,
        private AccessPolicy $access,
    ) {}

    public function allows(User $user, File $file): bool
    {
        $attachment = CompetitionAttachment::query()->with('competition')->where('file_id', $file->id)->first();
        $competition = $attachment?->competition;

        if ($attachment === null || $competition === null) {
            return false;
        }

        $viewer = $this->viewers->resolve($competition, Actor::forUser($user));

        if ($viewer === null) {
            return false;
        }

        if ($viewer->isIssuer() || $viewer->isParticipant()) {
            return true;
        }

        if ($attachment->kind !== AttachmentKind::InvitationDocument || $viewer->invitation === null) {
            return false;
        }

        $organization = Organization::query()->find($viewer->organizationId);

        return $organization !== null
            && $this->access->participationAccess($organization, $viewer->invitation)->state !== AccessState::Unavailable;
    }
}
