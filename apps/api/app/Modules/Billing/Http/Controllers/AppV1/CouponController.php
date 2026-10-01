<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Http\Requests\ValidateCouponRequest;
use App\Modules\Billing\Http\Resources\VoucherResource;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\CouponValidator;
use App\Modules\Billing\Services\PricingCalculator;
use App\Modules\Billing\Services\SponsorshipQuoter;
use App\Modules\Billing\Services\SubscriptionPurchaseResolver;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /billing/coupons/validate` (perm `billing.purchase`) and `GET /billing/vouchers` (perm
 * `billing.view`), API.md §1.7.
 */
final class CouponController extends BillingController
{
    /**
     * `CouponValidation` (API.md §2.10), with `discount_minor` computed for the given context.
     */
    public function validateCode(
        ValidateCouponRequest $request,
        CouponValidator $validator,
        PricingCalculator $pricing,
        SubscriptionPurchaseResolver $purchases,
        SponsorshipQuoter $quoter,
    ): JsonResponse {
        $organization = $this->organization($this->user($request));
        $coupon = $validator->validate($request->code(), $organization->id, $request->purpose());

        $base = $request->purpose() === PaymentPurpose::Subscription
            ? $this->subscriptionBase($request, $organization, $purchases)
            : $this->sponsorshipBase($request, $organization, $quoter);

        return $this->ok([
            'code' => $coupon->code,
            'kind' => $coupon->kind->value,
            'discount_type' => $coupon->discount_type->value,
            'percent_bps' => $coupon->percent_bps,
            'amount_minor' => $coupon->amount_minor,
            'balance_minor' => $coupon->balance_minor,
            'applies_to' => $coupon->applies_to->value,
            'valid_until' => Iso::format($coupon->valid_until),
            'discount_minor' => $pricing->discount($coupon, $base),
        ]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);
        $organization = $this->organization($this->user($request));

        $vouchers = Coupon::query()
            ->where('organization_id', $organization->id)
            ->where('kind', CouponKind::Voucher->value)
            ->orderByRaw('(is_active and coalesce(balance_minor, 0) > 0 and (valid_until is null or valid_until > now())) desc')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->ok(VoucherResource::collection($vouchers));
    }

    /**
     * The discount base of a subscription purchase: subtotal − upgrade credit. When the
     * purchase itself is not allowed (e.g. a downgrade), the plan subtotal is used.
     */
    private function subscriptionBase(ValidateCouponRequest $request, Organization $organization, SubscriptionPurchaseResolver $purchases): int
    {
        $plan = Plan::query()->wherePublicId(strtolower((string) $request->planId()))->first()
            ?? throw new ApiException('plan_not_available', 'billing.errors.plan_not_available', 422);

        try {
            $purchase = $purchases->resolve($organization->id, $plan, $request->billingInterval(), $request->seats());

            return $purchase->subtotalMinor() - $purchase->creditMinor;
        } catch (ApiException $e) {
            if (! in_array($e->errorCode, ['subscription_downgrade_not_allowed', 'subscription_renewal_too_early'], true)) {
                throw $e;
            }

            [$unit, $quantity] = $purchases->planLine($plan, $request->billingInterval(), $request->seats());

            return $unit * $quantity;
        }
    }

    /**
     * The discount base of a sponsorship checkout: the passes to buy for the draft invitations.
     */
    private function sponsorshipBase(ValidateCouponRequest $request, Organization $organization, SponsorshipQuoter $quoter): int
    {
        $competition = Competition::query()
            ->wherePublicId(strtolower((string) $request->competitionId()))
            ->where('organization_id', $organization->id)
            ->first() ?? throw new NotFoundHttpException;

        $candidates = $competition->invitations()
            ->where('status', InvitationStatus::Draft->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $quoter->quote($competition, $competition->sponsorship, $candidates)->price->subtotalMinor;
    }
}
