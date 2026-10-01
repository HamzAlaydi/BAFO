<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Services\BillingSettings;
use App\Modules\Billing\Services\SponsorshipQuoter;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `PUT /competitions/{competition}/sponsorship` `{mode: none|all|selected, max_passes?}`
 * (ARCHITECTURE §13.5 "Configuration", API.md §1.7):
 *
 * - requires the organization flag and the global setting (403 `sponsorship_not_enabled`);
 * - allowed in draft, scheduled, and live before the invitation cutoff (409
 *   `invalid_state_transition` otherwise);
 * - the row is created on the first mode other than none, with the pass price snapshot of the
 *   competition's direction; the price is re-read until the first funding, then frozen;
 * - once funded (or while a checkout holds passes) the mode cannot become none, and
 *   `max_passes` never drops below the pending, reserved and joined passes (409
 *   `sponsorship_locked`).
 *
 * Returns the sponsorship, or null when the mode is none and no row is left.
 */
final readonly class ConfigureSponsorship
{
    public function __construct(
        private BillingSettings $settings,
        private SponsorshipQuoter $quoter,
    ) {}

    public function handle(Competition $competition, ?SponsorshipMode $mode, ?int $maxPasses, Actor $actor): ?CompetitionSponsorship
    {
        if (! $competition->organization->sponsorship_enabled || ! $this->settings->sponsorshipEnabled()) {
            throw new ApiException('sponsorship_not_enabled', 'billing.errors.sponsorship_not_enabled', 403);
        }

        return DB::transaction(function () use ($competition, $mode, $maxPasses, $actor): ?CompetitionSponsorship {
            /** @var Competition $locked */
            $locked = Competition::query()->lockForUpdate()->findOrFail($competition->id);
            $this->ensureConfigurable($locked);

            $sponsorship = CompetitionSponsorship::query()->where('competition_id', $locked->id)->lockForUpdate()->first();

            if ($sponsorship === null) {
                return $mode === null ? null : $this->create($locked, $mode, $maxPasses, $actor);
            }

            $livePasses = $this->quoter->countPasses($sponsorship, PassStatus::live());
            $isLocked = $sponsorship->funded_passes > 0 || $livePasses > 0;

            if ($mode === null) {
                if ($isLocked || $sponsorship->status === SponsorshipStatus::Settled) {
                    throw new ApiException('sponsorship_locked', 'billing.errors.sponsorship_locked', 409);
                }

                AuditLogger::log('sponsorship.removed', $sponsorship, actor: $actor, organizationId: $locked->organization_id);
                $sponsorship->passes()->delete();
                $sponsorship->delete();

                return null;
            }

            if ($maxPasses !== null && $maxPasses < $livePasses) {
                throw new ApiException('sponsorship_locked', 'billing.errors.sponsorship_locked', 409, details: ['min_max_passes' => $livePasses]);
            }

            $sponsorship->fill(['mode' => $mode, 'max_passes' => $maxPasses]);

            if (! $isLocked) {
                $sponsorship->unit_price_minor = $this->settings->passPrice($locked->direction);
            }

            $sponsorship->save();

            AuditLogger::log('sponsorship.configured', $sponsorship, AuditLogger::diff($sponsorship, ['mode', 'max_passes', 'unit_price_minor']), actor: $actor, organizationId: $locked->organization_id);

            return $sponsorship;
        });
    }

    private function create(Competition $competition, SponsorshipMode $mode, ?int $maxPasses, Actor $actor): CompetitionSponsorship
    {
        $sponsorship = CompetitionSponsorship::query()->create([
            'competition_id' => $competition->id,
            'organization_id' => $competition->organization_id,
            'mode' => $mode,
            'max_passes' => $maxPasses,
            'unit_price_minor' => $this->settings->passPrice($competition->direction),
            'vat_rate_bp' => $this->settings->vatRateBp(),
            'funded_passes' => 0,
            'status' => SponsorshipStatus::Draft,
            'configured_by_user_id' => $actor->userId ?? $competition->created_by_user_id,
        ]);

        AuditLogger::log('sponsorship.configured', $sponsorship, [
            'mode' => ['from' => 'none', 'to' => $mode->value],
            'max_passes' => ['from' => null, 'to' => $maxPasses],
        ], actor: $actor, organizationId: $competition->organization_id);

        return $sponsorship;
    }

    private function ensureConfigurable(Competition $competition): void
    {
        $open = match ($competition->status) {
            CompetitionStatus::Draft, CompetitionStatus::Scheduled => true,
            CompetitionStatus::Live => $competition->invitation_cutoff_at === null
                || CarbonImmutable::now()->lessThan($competition->invitation_cutoff_at),
            default => false,
        };

        if (! $open) {
            throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $competition->status->value]);
        }
    }
}
