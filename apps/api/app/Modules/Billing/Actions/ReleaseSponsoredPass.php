<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;

/**
 * Frees an invitation's pass when the invitation is declined or revoked (ARCHITECTURE §13.5
 * "Release", §6.3): a `reserved` pass → `released` (its slot is free again), a `pending`
 * pass → `void`. It runs inside the Competitions transaction (a synchronous listener), so it
 * is DB-only and takes the pass row lock.
 */
final class ReleaseSponsoredPass
{
    public function handle(Invitation $invitation, PassReleaseReason $reason, Actor $actor): ?SponsoredPass
    {
        $pass = SponsoredPass::query()
            ->where('invitation_id', $invitation->id)
            ->whereIn('status', [PassStatus::Pending->value, PassStatus::Reserved->value])
            ->lockForUpdate()
            ->first();

        if ($pass === null) {
            return null;
        }

        $now = CarbonImmutable::now();

        if ($pass->status === PassStatus::Pending) {
            $pass->forceFill(['status' => PassStatus::Void, 'voided_at' => $now, 'hold_expires_at' => null])->save();
            AuditLogger::log('sponsored_pass.voided', $pass, meta: ['reason' => $reason->value], actor: $actor, organizationId: $this->issuerId($pass));

            return $pass;
        }

        $pass->forceFill(['status' => PassStatus::Released, 'release_reason' => $reason, 'released_at' => $now])->save();
        AuditLogger::log('sponsored_pass.released', $pass, meta: ['reason' => $reason->value], actor: $actor, organizationId: $this->issuerId($pass));

        return $pass;
    }

    private function issuerId(SponsoredPass $pass): ?int
    {
        return $pass->sponsorship?->organization_id;
    }
}
