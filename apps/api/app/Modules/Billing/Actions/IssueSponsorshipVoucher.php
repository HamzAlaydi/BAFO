<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Events\VoucherIssued;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin "Issue voucher" for the unused passes of a settled sponsorship (ARCHITECTURE §13.5
 * "Voucher", D15): a fixed voucher of `unused_count × unit_price_minor` (excl. VAT) for the
 * sponsor, usable on any purchase for 12 months. Once per sponsorship.
 */
final class IssueSponsorshipVoucher
{
    public function handle(CompetitionSponsorship $sponsorship, Actor $actor, ?string $reason = null): Coupon
    {
        return DB::transaction(static function () use ($sponsorship, $actor, $reason): Coupon {
            /** @var CompetitionSponsorship $locked */
            $locked = CompetitionSponsorship::query()->lockForUpdate()->findOrFail($sponsorship->id);

            if ($locked->status !== SponsorshipStatus::Settled || (int) $locked->unused_count <= 0 || $locked->voucher_coupon_id !== null) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $locked->status->value]);
            }

            $now = CarbonImmutable::now();
            $value = (int) $locked->unused_count * $locked->unit_price_minor;
            $reference = $locked->competition->reference_no ?? $locked->competition->title;
            $defaultReason = __('billing.voucher.reason', ['reference' => $reference], 'en');

            $voucher = Coupon::query()->create([
                'code' => self::newCode(),
                'kind' => CouponKind::Voucher,
                'discount_type' => DiscountType::Fixed,
                'amount_minor' => $value,
                'balance_minor' => $value,
                'applies_to' => CouponScope::Any,
                'organization_id' => $locked->organization_id,
                'max_redemptions' => null,
                'per_organization_limit' => null,
                'valid_from' => $now,
                'valid_until' => $now->addMonths(12),
                'is_active' => true,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : (is_string($defaultReason) ? $defaultReason : null),
                'source_competition_id' => $locked->competition_id,
                'created_by_admin_id' => $actor->adminId,
            ]);

            $locked->forceFill(['voucher_coupon_id' => $voucher->id])->save();

            AuditLogger::log('voucher.issued', $voucher, meta: [
                'amount_minor' => $value,
                'sponsorship_id' => $locked->public_id,
            ], actor: $actor, organizationId: $locked->organization_id);

            VoucherIssued::dispatch($voucher);

            return $voucher;
        });
    }

    /**
     * `V-` + 10 random `[A-Z0-9]` (ARCHITECTURE §5.7), unique.
     */
    public static function newCode(): string
    {
        do {
            $code = 'V-'.Str::upper(Str::random(10));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
