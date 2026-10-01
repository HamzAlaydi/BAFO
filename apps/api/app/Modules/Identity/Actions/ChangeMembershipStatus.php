<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Events\MemberUpdated;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Services\SeatGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Admin panel (ARCHITECTURE §6.6, §16): deactivates or reactivates a membership (`active ↔
 * inactive`), the owner's included. Reactivation takes a free seat (409 `seat_limit_reached`).
 */
final readonly class ChangeMembershipStatus
{
    public function __construct(private SeatGuard $seats) {}

    public function handle(Membership $membership, MembershipStatus $to, Actor $actor): Membership
    {
        return DB::transaction(function () use ($membership, $to, $actor): Membership {
            $membership->loadMissing('organization');
            $organization = $this->seats->lock($membership->organization);

            /** @var Membership $membership */
            $membership = Membership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $from = $membership->status;

            if ($from === $to) {
                return $membership;
            }

            if ($from === MembershipStatus::Invited || $to === MembershipStatus::Invited) {
                throw new ApiException(
                    errorCode: 'invalid_state_transition',
                    status: 409,
                    details: ['from' => $from->value, 'to' => $to->value],
                );
            }

            if ($to === MembershipStatus::Active) {
                $this->seats->assertFreeSeat($organization);
            }

            $membership->forceFill(['status' => $to])->save();
            $changes = AuditLogger::diff($membership);

            AuditLogger::log('member.updated', $membership, $changes, actor: $actor, organizationId: $membership->organization_id);

            event(new MemberUpdated($membership, $actor, $changes));

            return $membership;
        });
    }
}
