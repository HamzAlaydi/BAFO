<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Events\SponsorshipSettled;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Settles a competition's sponsorship at close or cancel (ARCHITECTURE §13.5 "Settlement"),
 * under the sponsorship row lock and exactly once:
 *
 *   reserved → unused, pending → void;
 *   unused_count = funded_passes − joined passes;
 *   status = settled; SponsorshipSettled.
 *
 * The admin then issues a voucher for the unused passes (IssueSponsorshipVoucher).
 */
final class SettleCompetitionSponsorship
{
    public function handle(Competition $competition, Actor $actor): ?CompetitionSponsorship
    {
        return DB::transaction(static function () use ($competition, $actor): ?CompetitionSponsorship {
            $sponsorship = CompetitionSponsorship::query()->where('competition_id', $competition->id)->lockForUpdate()->first();

            if ($sponsorship === null || $sponsorship->status === SponsorshipStatus::Settled) {
                return $sponsorship;
            }

            $now = CarbonImmutable::now();

            SponsoredPass::query()
                ->where('sponsorship_id', $sponsorship->id)
                ->where('status', PassStatus::Reserved->value)
                ->update(['status' => PassStatus::Unused->value, 'settled_at' => $now, 'updated_at' => $now]);

            SponsoredPass::query()
                ->where('sponsorship_id', $sponsorship->id)
                ->where('status', PassStatus::Pending->value)
                ->update(['status' => PassStatus::Void->value, 'voided_at' => $now, 'hold_expires_at' => null, 'updated_at' => $now]);

            $joined = SponsoredPass::query()
                ->where('sponsorship_id', $sponsorship->id)
                ->where('status', PassStatus::Joined->value)
                ->count();

            $sponsorship->forceFill([
                'status' => SponsorshipStatus::Settled,
                'settled_at' => $now,
                'unused_count' => max(0, $sponsorship->funded_passes - $joined),
            ])->save();

            AuditLogger::log('sponsorship.settled', $sponsorship, meta: [
                'funded_passes' => $sponsorship->funded_passes,
                'joined' => $joined,
                'unused_count' => $sponsorship->unused_count,
            ], actor: $actor, organizationId: $sponsorship->organization_id);

            SponsorshipSettled::dispatch($sponsorship);

            return $sponsorship;
        });
    }
}
