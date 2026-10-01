<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Events\MemberUpdated;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MembershipFlagGuard;
use App\Modules\Identity\Services\SeatGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /team/members/{membership}` (ARCHITECTURE §13.10): role, `can_award`, `can_purchase` and
 * `status` (`active` ↔ `inactive`). The owner cannot be changed, nobody changes themselves,
 * reactivating takes a free seat, and a flag is granted only by an editor holding it
 * (MembershipFlagGuard, 422 on the flag).
 */
final readonly class UpdateTeamMember
{
    public function __construct(
        private SeatGuard $seats,
        private MembershipFlagGuard $flags,
    ) {}

    /**
     * @param  array{role?: string, can_award?: bool, can_purchase?: bool, status?: string}  $attributes
     */
    public function handle(Membership $membership, array $attributes, User $editor, Actor $actor): Membership
    {
        return DB::transaction(function () use ($membership, $attributes, $editor, $actor): Membership {
            $membership->loadMissing('organization');
            $this->seats->lock($membership->organization);

            /** @var Membership $membership */
            $membership = Membership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            self::assertEditable($membership, $editor);
            $this->flags->assertGrantable($editor, $attributes, [
                'can_award' => $membership->can_award,
                'can_purchase' => $membership->can_purchase,
            ]);

            if (isset($attributes['status']) && $attributes['status'] !== $membership->status->value) {
                $to = MembershipStatus::from($attributes['status']);

                if ($membership->status === MembershipStatus::Invited) {
                    throw new ApiException(
                        errorCode: 'invalid_state_transition',
                        status: 409,
                        details: ['from' => $membership->status->value, 'to' => $to->value],
                    );
                }

                if ($to === MembershipStatus::Active) {
                    $this->seats->assertFreeSeat($membership->organization()->firstOrFail());
                }
            }

            $membership->fill($attributes)->save();
            $changes = AuditLogger::diff($membership);

            if ($changes !== []) {
                AuditLogger::log('member.updated', $membership, $changes, actor: $actor, organizationId: $membership->organization_id);

                event(new MemberUpdated($membership, $actor, $changes));
            }

            return $membership->load('user.avatarFile');
        });
    }

    /**
     * @throws ApiException `cannot_modify_owner` or `cannot_modify_self` (409)
     */
    public static function assertEditable(Membership $membership, User $editor): void
    {
        if ($membership->isOwner()) {
            throw IdentityError::make('cannot_modify_owner');
        }

        if ($membership->user_id === $editor->id) {
            throw IdentityError::make('cannot_modify_self');
        }
    }
}
