<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Actions\ConfigureSponsorship;
use App\Modules\Billing\Actions\CreateSponsorshipCheckout;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Http\Requests\SponsorshipQuoteRequest;
use App\Modules\Billing\Http\Requests\StoreSponsorshipCheckoutRequest;
use App\Modules\Billing\Http\Requests\UpdateSponsorshipRequest;
use App\Modules\Billing\Http\Resources\PaymentResource;
use App\Modules\Billing\Services\CouponValidator;
use App\Modules\Billing\Services\SponsorshipPresenter;
use App\Modules\Billing\Services\SponsorshipQuoter;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;

/**
 * R4 sponsorship of a competition (API.md §1.7 "Sponsorship", ARCHITECTURE §13.5). The route
 * authorizes through the SponsorshipPolicy gate abilities: issuer members see it; managing
 * needs `competitions.manage`; checkout also needs `billing.purchase`.
 */
final class SponsorshipController extends BillingController
{
    public function show(Competition $competition, SponsorshipPresenter $presenter): JsonResponse
    {
        return $this->ok($presenter->toArray($competition, $competition->sponsorship));
    }

    public function update(UpdateSponsorshipRequest $request, Competition $competition, ConfigureSponsorship $configure, SponsorshipPresenter $presenter): JsonResponse
    {
        $sponsorship = $configure->handle($competition, $request->mode(), $request->maxPasses(), CurrentActor::get());

        return $this->ok($presenter->toArray($competition->refresh(), $sponsorship));
    }

    /**
     * The quote for the current draft invitations; after publish `passes_to_buy = 0` plus the
     * current funded and free slots (invite more through checkout with `intent: invite`).
     */
    public function quote(SponsorshipQuoteRequest $request, Competition $competition, SponsorshipQuoter $quoter, CouponValidator $coupons): JsonResponse
    {
        $couponCode = $request->couponCode();
        $coupon = $couponCode === null ? null : $coupons->validate($couponCode, $competition->organization_id, PaymentPurpose::Sponsorship);

        $candidates = $competition->status === CompetitionStatus::Draft
            ? $competition->invitations()
                ->where('status', InvitationStatus::Draft->value)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
            : [];

        return $this->ok($quoter->quote($competition, $competition->sponsorship, $candidates, $coupon)->toArray());
    }

    public function checkout(StoreSponsorshipCheckoutRequest $request, Competition $competition, CreateSponsorshipCheckout $checkout): JsonResponse
    {
        $payment = $checkout->handle(
            $this->user($request),
            $competition,
            $request->intent(),
            $request->rows(),
            $request->couponCode(),
            $request->returnUrl(),
            $request->idempotencyKey(),
            CurrentActor::get(),
        );

        return $this->created(PaymentResource::make($payment));
    }
}
