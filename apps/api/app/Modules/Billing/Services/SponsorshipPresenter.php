<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Models\Competition;
use App\Support\Money\Money;

/**
 * Presents API.md §2.10 `Sponsorship` of a competition. Without a row it is `{"mode": "none", …}` with
 * the current pass price of the competition's direction. Other modules may embed
 * `summary()` as the issuer projection's `sponsorship` (API.md §2.6).
 */
final readonly class SponsorshipPresenter
{
    public function __construct(private BillingSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(Competition $competition, ?CompetitionSponsorship $sponsorship): array
    {
        $counts = array_fill_keys(array_map(static fn (PassStatus $s): string => $s->value, PassStatus::cases()), 0);

        if ($sponsorship !== null) {
            SponsoredPass::query()
                ->where('sponsorship_id', $sponsorship->id)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->each(static function (mixed $count, mixed $status) use (&$counts): void {
                    $counts[(string) $status] = (int) $count;
                });
        }

        $funded = $sponsorship->funded_passes ?? 0;
        $counts['free_slots'] = max(0, $funded - $counts[PassStatus::Reserved->value] - $counts[PassStatus::Joined->value]);

        return [
            'mode' => $sponsorship->mode->value ?? 'none',
            'max_passes' => $sponsorship?->max_passes,
            'unit_price_minor' => $sponsorship->unit_price_minor ?? $this->settings->passPrice($competition->direction),
            'vat_rate_bp' => $sponsorship->vat_rate_bp ?? $this->settings->vatRateBp(),
            'currency' => Money::CURRENCY,
            'status' => $sponsorship?->status->value,
            'funded_passes' => $funded,
            'enabled' => $competition->organization->sponsorship_enabled && $this->settings->sponsorshipEnabled(),
            'counts' => $counts,
            'unused_count' => $sponsorship?->unused_count,
            'voucher' => $sponsorship?->voucherCoupon === null ? null : ['code' => $sponsorship->voucherCoupon->code],
        ];
    }

    /**
     * The issuer projection's `sponsorship` (API.md §2.6): `{"mode", "status", "funded_passes",
     * "free_slots"}`, or null when the mode is none.
     *
     * @return array{mode: string, status: string, funded_passes: int, free_slots: int}|null
     */
    public static function summary(?CompetitionSponsorship $sponsorship): ?array
    {
        if ($sponsorship === null) {
            return null;
        }

        $occupied = SponsoredPass::query()
            ->where('sponsorship_id', $sponsorship->id)
            ->whereIn('status', PassStatus::occupying())
            ->count();

        return [
            'mode' => $sponsorship->mode->value,
            'status' => $sponsorship->status->value,
            'funded_passes' => $sponsorship->funded_passes,
            'free_slots' => max(0, $sponsorship->funded_passes - $occupied),
        ];
    }
}
