<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Data\SponsorshipQuote;
use App\Modules\Billing\Data\SponsorshipQuoteLine;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\QuoteLineReason;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;

/**
 * The sponsorship quote of ARCHITECTURE §13.5:
 *
 *     covered    = mode all ? candidates : candidates where sponsored_requested (mode none ⇒ none)
 *     need       = covered where coverage ≠ own_plan and no pass in {pending, reserved, joined}
 *     max_passes → only the first `max − live passes` of need; the rest are cap_reached
 *     free       = funded_passes − passes in {reserved, joined}
 *     to_reserve = min(|need|, free);  to_buy = |need| − to_reserve
 *     subtotal   = to_buy × unit_price; discount; vat; total
 *
 * Candidates may be unsaved invitations (invite rows): they have no id and no pass yet.
 */
final readonly class SponsorshipQuoter
{
    public function __construct(
        private BillingSettings $settings,
        private SubscriptionLookup $subscriptions,
        private PricingCalculator $pricing,
    ) {}

    /**
     * @param  iterable<Invitation>  $candidates  in quote order (created_at, then row order)
     */
    public function quote(Competition $competition, ?CompetitionSponsorship $sponsorship, iterable $candidates, ?Coupon $coupon = null): SponsorshipQuote
    {
        $list = [];

        foreach ($candidates as $candidate) {
            $list[] = $candidate;
        }

        $mode = $sponsorship?->mode;
        $livePassInvitationIds = $this->livePassInvitationIds($list);
        $coveredUntil = $this->subscriptions->coveredUntil(array_values(array_filter(
            array_map(static fn (Invitation $i): ?int => $i->organization_id, $list),
        )));
        $organizationNames = $this->organizationNames($list);

        $livePasses = $sponsorship === null ? 0 : $this->countPasses($sponsorship, PassStatus::live());
        $allowed = $sponsorship?->max_passes === null ? PHP_INT_MAX : max(0, $sponsorship->max_passes - $livePasses);

        $lines = [];
        $needs = [];

        foreach ($list as $invitation) {
            $ownPlan = DbAccessPolicy::ownPlanCovers($invitation->organization_id, $competition, $coveredUntil);
            $hasPass = $invitation->exists && isset($livePassInvitationIds[$invitation->id]);
            $selected = $mode === SponsorshipMode::All || ($mode === SponsorshipMode::Selected && $invitation->sponsored_requested);

            [$coverage, $reason] = match (true) {
                $hasPass => [Coverage::Sponsored, null],
                ! $selected => [$ownPlan ? Coverage::OwnPlan : Coverage::None, $mode === null ? null : QuoteLineReason::NotSelected],
                $ownPlan => [Coverage::OwnPlan, QuoteLineReason::OwnPlan],
                count($needs) >= $allowed => [Coverage::None, QuoteLineReason::CapReached],
                default => [Coverage::Sponsored, null],
            };

            if (! $hasPass && $selected && ! $ownPlan && $reason === null) {
                $needs[] = $invitation;
            }

            $lines[] = new SponsorshipQuoteLine(
                $invitation->exists ? $invitation->public_id : null,
                $invitation->email,
                $invitation->organization_id === null ? null : ($organizationNames[$invitation->organization_id] ?? null),
                $coverage,
                $reason,
            );
        }

        $funded = $sponsorship->funded_passes ?? 0;
        $free = $sponsorship === null ? 0 : max(0, $funded - $this->countPasses($sponsorship, PassStatus::occupying()));
        $toReserve = min(count($needs), $free);
        $toBuy = count($needs) - $toReserve;
        $unit = $sponsorship->unit_price_minor ?? $this->settings->passPrice($competition->direction);
        $vatRate = $sponsorship->vat_rate_bp ?? $this->settings->vatRateBp();

        return new SponsorshipQuote(
            mode: $mode,
            maxPasses: $sponsorship?->max_passes,
            unitPriceMinor: $unit,
            fundedPasses: $funded,
            freeSlots: $free,
            lines: $lines,
            needs: $needs,
            passesToReserve: $toReserve,
            passesToBuy: $toBuy,
            price: $this->pricing->breakdown($toBuy * $unit, 0, $coupon, $vatRate),
        );
    }

    /**
     * @param  list<string>  $statuses
     */
    public function countPasses(CompetitionSponsorship $sponsorship, array $statuses): int
    {
        return SponsoredPass::query()
            ->where('sponsorship_id', $sponsorship->id)
            ->whereIn('status', $statuses)
            ->count();
    }

    /**
     * @param  list<Invitation>  $invitations
     * @return array<int, int> invitation id → invitation id, for saved invitations with a live pass
     */
    private function livePassInvitationIds(array $invitations): array
    {
        $ids = array_values(array_filter(array_map(static fn (Invitation $i): ?int => $i->exists ? $i->id : null, $invitations)));

        if ($ids === []) {
            return [];
        }

        return SponsoredPass::query()
            ->whereIn('invitation_id', $ids)
            ->whereIn('status', PassStatus::live())
            ->pluck('invitation_id', 'invitation_id')
            ->mapWithKeys(static fn (mixed $id): array => [(int) $id => (int) $id])
            ->all();
    }

    /**
     * @param  list<Invitation>  $invitations
     * @return array<int, string>
     */
    private function organizationNames(array $invitations): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (Invitation $i): ?int => $i->organization_id, $invitations))));

        if ($ids === []) {
            return [];
        }

        return Organization::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }
}
