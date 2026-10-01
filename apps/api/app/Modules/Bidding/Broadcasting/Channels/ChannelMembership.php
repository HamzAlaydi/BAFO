<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Broadcasting\Channels;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;

/**
 * The organization a user may listen for: the one of its active membership (one per user,
 * ARCHITECTURE §5.3). Channel auth does not run the app v1 account gate (§8.1), so the gate's
 * conditions are checked here: an active user with a verified e-mail, an active membership and
 * an active organization (SECURITY_REVIEW S-06: a suspended organization listens to nothing).
 */
final class ChannelMembership
{
    public static function organizationId(User $user): ?int
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return null;
        }

        $organizationId = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('organization', static fn ($query) => $query->where('status', OrganizationStatus::Active->value))
            ->value('organization_id');

        return $organizationId === null ? null : (int) $organizationId;
    }
}
