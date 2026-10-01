<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Admin "Grant pass" for one invitation (ARCHITECTURE §6.3 `admin_grant`, §16): funds one
 * more slot and reserves it for the invitation at once. The competition must have a
 * sponsorship (mode all or selected) that is not settled, and the invitation must not hold a
 * live pass or be closed.
 */
final class GrantSponsoredPass
{
    public function handle(Invitation $invitation, Actor $actor): SponsoredPass
    {
        return DB::transaction(static function () use ($invitation, $actor): SponsoredPass {
            $sponsorship = CompetitionSponsorship::query()
                ->where('competition_id', $invitation->competition_id)
                ->lockForUpdate()
                ->first();

            $open = in_array($invitation->status, [InvitationStatus::Draft, InvitationStatus::Sent, InvitationStatus::Viewed], true);
            $hasPass = SponsoredPass::query()
                ->where('invitation_id', $invitation->id)
                ->whereIn('status', PassStatus::live())
                ->exists();

            if ($sponsorship === null || $sponsorship->status === SponsorshipStatus::Settled || ! $open || $hasPass) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $invitation->status->value]);
            }

            $sponsorship->forceFill([
                'funded_passes' => $sponsorship->funded_passes + 1,
                'status' => SponsorshipStatus::Active,
            ])->save();

            $pass = SponsoredPass::query()->create([
                'sponsorship_id' => $sponsorship->id,
                'competition_id' => $invitation->competition_id,
                'invitation_id' => $invitation->id,
                'organization_id' => $invitation->organization_id,
                'source' => PassSource::AdminGrant,
                'status' => PassStatus::Reserved,
                'reserved_at' => CarbonImmutable::now(),
            ]);

            AuditLogger::log('sponsored_pass.granted', $pass, meta: [
                'invitation_id' => $invitation->public_id,
            ], actor: $actor, organizationId: $sponsorship->organization_id);

            return $pass;
        });
    }
}
