<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The §16 dashboard counters (read-only queries over other modules' tables).
 */
final class PlatformMetrics
{
    /** Organizations that are not deleted (active or suspended). */
    public function organizations(): int
    {
        return Organization::query()->where('status', '!=', OrganizationStatus::Deleted->value)->count();
    }

    /** Current subscriptions: `status = active AND starts_at <= now < ends_at` (§5.7). */
    public function activeSubscriptions(): int
    {
        $now = CarbonImmutable::now();

        return Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->count();
    }

    public function liveCompetitions(): int
    {
        return Competition::query()->where('status', CompetitionStatus::Live->value)->count();
    }

    /**
     * Succeeded payments paid since midnight in Riyadh, and their total.
     *
     * CONTRACT-GAP: "payments today" is read as succeeded payments whose `paid_at` falls on the
     * current Riyadh calendar day (displays use Asia/Riyadh, §1).
     *
     * @return array{0: int, 1: int}
     */
    public function paymentsToday(): array
    {
        $query = Payment::query()
            ->where('status', PaymentStatus::Succeeded->value)
            ->where('paid_at', '>=', CarbonImmutable::now('Asia/Riyadh')->startOfDay()->utc());

        return [(clone $query)->count(), (int) (clone $query)->sum('total_minor')];
    }

    public function failedEInvoices(): int
    {
        return self::failedEInvoicesQuery()->count();
    }

    public function sponsorshipsAwaitingVoucher(): int
    {
        return self::awaitingVoucherQuery()->count();
    }

    /**
     * Invoices the e-invoicing provider rejected or that failed to clear.
     *
     * @return Builder<Invoice>
     */
    public static function failedEInvoicesQuery(): Builder
    {
        return Invoice::query()->whereIn('einvoice_status', [EInvoiceStatus::Rejected->value, EInvoiceStatus::Failed->value]);
    }

    /**
     * Settled sponsorships with unused passes and no voucher yet (Billing handoff).
     *
     * @return Builder<CompetitionSponsorship>
     */
    public static function awaitingVoucherQuery(): Builder
    {
        return CompetitionSponsorship::query()
            ->where('status', SponsorshipStatus::Settled->value)
            ->where('unused_count', '>', 0)
            ->whereNull('voucher_coupon_id');
    }
}
