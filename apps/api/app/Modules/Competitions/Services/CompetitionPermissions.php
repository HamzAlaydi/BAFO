<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Enums\CompetitionStatus as S;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use Illuminate\Support\Facades\Date;

/**
 * The `permissions` object of the Competition resource (API.md §2.6), from ARCHITECTURE §8.3 and
 * the state machines. Clients render from it and never recompute entitlement. Issuer flags are
 * false for participants and invitees, and the participant-side flags are false for the issuer.
 * The release scope (RELEASE_SCOPE.md §1.5) turns `can_extend`, `can_start_bafo` and
 * `can_manage_sponsorship` off while `extend_competition`, `bafo_round` or `sponsorship` is
 * hidden; this is a response projection only (the admin panel and the Actions never read it).
 */
final class CompetitionPermissions
{
    public const array FLAGS = [
        'can_edit', 'can_delete', 'can_publish', 'can_invite', 'can_extend', 'can_cancel', 'can_start_bafo',
        'can_award', 'can_revoke_award', 'can_close_without_award', 'can_manage_sponsorship', 'can_comment',
        'can_join', 'can_decline', 'can_submit_offer',
    ];

    /**
     * `competitions.manage` on a competition (§8.1): `competitions.manage_all`, or
     * `competitions.create` on a competition the user created.
     */
    public static function canManage(User $user, Competition $competition): bool
    {
        return $user->hasPermission(Permission::CompetitionsManageAll)
            || ($user->hasPermission(Permission::CompetitionsCreate) && $competition->created_by_user_id === $user->id);
    }

    public static function canAward(User $user): bool
    {
        return $user->hasPermission(Permission::CompetitionsAward);
    }

    /**
     * Inviting (and configuring sponsorship) is open on a draft, and on a published competition
     * before `invitation_cutoff_at`.
     */
    public static function invitationsOpen(Competition $competition): bool
    {
        if ($competition->status === S::Draft) {
            return true;
        }

        return in_array($competition->status, [S::Scheduled, S::Live], true)
            && $competition->invitation_cutoff_at !== null
            && Date::now()->lessThan($competition->invitation_cutoff_at);
    }

    /**
     * @param  array{accepting_offers?: bool}|array<string, mixed>|null  $live  the participant snapshot
     * @param  array{state: string}|array<string, mixed>|null  $access  the invitee access object
     * @return array<string, bool>
     */
    public static function for(Competition $competition, Viewer $viewer, ?User $user, ?array $live = null, ?array $access = null): array
    {
        $flags = array_fill_keys(self::FLAGS, false);
        $status = $competition->status;
        $commentsOpen = in_array($status, [S::Scheduled, S::Live], true);

        if ($viewer->isIssuer()) {
            $manage = $user !== null && self::canManage($user, $competition);
            $award = $user !== null && self::canAward($user);
            $features = app(FeatureFlags::class);

            return [
                ...$flags,
                'can_edit' => $manage && in_array($status, [S::Draft, S::Scheduled, S::Live], true),
                'can_delete' => $manage && $status === S::Draft,
                'can_publish' => $manage && $status === S::Draft,
                'can_invite' => $manage && self::invitationsOpen($competition),
                'can_extend' => $manage && $status === S::Live && $features->enabled(Feature::ExtendCompetition),
                'can_cancel' => $manage && in_array($status, [S::Scheduled, S::Live, S::BafoRound], true),
                'can_start_bafo' => $award && $status === S::Closed && $competition->bafo_round_enabled
                    && $features->enabled(Feature::BafoRound)
                    && ! BafoRound::query()->where('competition_id', $competition->id)->exists(),
                'can_award' => $award && $status === S::Closed,
                'can_revoke_award' => $award && $status === S::Awarded,
                'can_close_without_award' => $award && $status === S::Closed,
                'can_manage_sponsorship' => $manage && self::invitationsOpen($competition) && $features->enabled(Feature::Sponsorship),
                'can_comment' => $commentsOpen,
            ];
        }

        if ($viewer->isParticipant()) {
            return [
                ...$flags,
                'can_comment' => $commentsOpen,
                'can_submit_offer' => ($live['accepting_offers'] ?? false) === true
                    && $user !== null && $user->hasPermission(Permission::ParticipationSubmitOffers),
            ];
        }

        $pending = in_array($viewer->invitation?->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true);

        return [
            ...$flags,
            'can_join' => $pending && ($access['state'] ?? null) === 'join_required',
            'can_decline' => $pending,
        ];
    }
}
