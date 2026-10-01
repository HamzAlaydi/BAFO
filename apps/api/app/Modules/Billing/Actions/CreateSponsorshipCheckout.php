<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\PaymentLineKind;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SponsorshipIntent;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Services\BillingSettings;
use App\Modules\Billing\Services\CheckoutGuard;
use App\Modules\Billing\Services\CouponValidator;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Modules\Billing\Services\InvitationRowResolver;
use App\Modules\Billing\Services\LineDescriptions;
use App\Modules\Billing\Services\SponsorshipQuoter;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `POST /competitions/{competition}/sponsorship/checkout` (API.md §1.7, ARCHITECTURE §13.5).
 *
 * - `intent: publish` (a draft): the quote over the draft invitations; the payment buys
 *   `to_buy` passes, held as `pending` passes on the first `to_buy` invitations of `need`.
 * - `intent: invite` (scheduled or live, before the cutoff): the rows are validated like
 *   `POST …/invitations` and quoted as new invitations; they are stored in the payment
 *   metadata, and no invitation or pass exists before the payment succeeds.
 *
 * Nothing to buy → 409 `sponsorship_already_funded` (publish or invite directly). The
 * competition and sponsorship rows are locked, so two checkouts never buy the same passes.
 */
final readonly class CreateSponsorshipCheckout
{
    public function __construct(
        private CheckoutGuard $guard,
        private SponsorshipQuoter $quoter,
        private CouponValidator $coupons,
        private InvitationRowResolver $rows,
        private BillingSettings $settings,
        private PaymentGatewayManager $gateways,
        private OpenGatewayCheckout $openCheckout,
    ) {}

    /**
     * @param  list<array{email?: string|null, organization_id?: string|null, vendor_id?: string|null, name?: string|null}>  $rows
     */
    public function handle(
        User $payer,
        Competition $competition,
        SponsorshipIntent $intent,
        array $rows,
        ?string $couponCode,
        string $returnUrl,
        ?string $idempotencyKey,
        Actor $actor,
    ): Payment {
        $organization = $competition->organization;

        if (! $organization->sponsorship_enabled || ! $this->settings->sponsorshipEnabled()) {
            throw new ApiException('sponsorship_not_enabled', 'billing.errors.sponsorship_not_enabled', 403);
        }

        $this->guard->check($actor, $organization, $returnUrl);

        if ($idempotencyKey !== null) {
            $existing = Payment::query()
                ->where('organization_id', $organization->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $now = CarbonImmutable::now();
        $coupon = $couponCode === null || trim($couponCode) === ''
            ? null
            : $this->coupons->validate($couponCode, $organization->id, PaymentPurpose::Sponsorship, $now);

        $payment = DB::transaction(function () use ($payer, $competition, $intent, $rows, $coupon, $returnUrl, $idempotencyKey, $now, $actor): Payment {
            /** @var Competition $locked */
            $locked = Competition::query()->lockForUpdate()->findOrFail($competition->id);
            $this->ensureIntentAllowed($locked, $intent, $now);

            $sponsorship = CompetitionSponsorship::query()->where('competition_id', $locked->id)->lockForUpdate()->first();
            $resolvedRows = [];

            if ($intent === SponsorshipIntent::Invite) {
                $resolvedRows = $this->rows->resolve($locked, $rows);
                $candidates = $this->rows->candidates($locked, $resolvedRows);
            } else {
                $candidates = $locked->invitations()
                    ->where('status', InvitationStatus::Draft->value)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();
            }

            $quote = $this->quoter->quote($locked, $sponsorship, $candidates, $coupon);

            if ($sponsorship === null || $quote->passesToBuy === 0) {
                throw new ApiException('sponsorship_already_funded', 'billing.errors.sponsorship_already_funded', 409);
            }

            $price = $quote->price;
            $payment = Payment::query()->create([
                'organization_id' => $locked->organization_id,
                'created_by_user_id' => $payer->id,
                'purpose' => PaymentPurpose::Sponsorship,
                'status' => PaymentStatus::Pending,
                'gateway' => $this->gateways->defaultName(),
                'currency' => 'SAR',
                'subtotal_minor' => $price->subtotalMinor,
                'discount_minor' => $price->discountMinor,
                'credit_minor' => 0,
                'vat_rate_bp' => $price->vatRateBp,
                'vat_minor' => $price->vatMinor,
                'total_minor' => $price->totalMinor,
                'coupon_id' => $coupon?->id,
                'idempotency_key' => $idempotencyKey,
                'return_url' => $returnUrl,
                'metadata' => ['competition_id' => $locked->id, 'intent' => $intent->value],
                'expires_at' => $now->addMinutes($this->settings->checkoutHoldMinutes()),
            ]);

            $payment->lines()->create([
                'kind' => PaymentLineKind::SponsoredPass,
                'description' => LineDescriptions::sponsoredPass($locked),
                'quantity' => $quote->passesToBuy,
                'unit_price_minor' => $quote->unitPriceMinor,
                'net_minor' => $price->subtotalMinor,
                'ref_type' => $sponsorship->getMorphClass(),
                'ref_id' => $sponsorship->id,
            ]);

            $metadata = $payment->metadata;

            if ($intent === SponsorshipIntent::Publish) {
                // The first `to_buy` invitations of `need` are held on the payment; the rest take the
                // free slots at publish.
                $bought = array_slice($quote->needs, 0, $quote->passesToBuy);
                $passIds = [];

                foreach ($bought as $invitation) {
                    $passIds[] = SponsoredPass::query()->create([
                        'sponsorship_id' => $sponsorship->id,
                        'competition_id' => $locked->id,
                        'invitation_id' => $invitation->id,
                        'organization_id' => $invitation->organization_id,
                        'payment_id' => $payment->id,
                        'source' => PassSource::Purchase,
                        'status' => PassStatus::Pending,
                        'hold_expires_at' => $payment->expires_at,
                    ])->id;
                }

                $metadata['pass_ids'] = $passIds;
            } else {
                $metadata['rows'] = $resolvedRows;
            }

            $payment->metadata = $metadata;
            $payment->save();

            AuditLogger::log('payment.created', $payment, meta: [
                'purpose' => PaymentPurpose::Sponsorship->value,
                'intent' => $intent->value,
                'passes' => $quote->passesToBuy,
                'total_minor' => $price->totalMinor,
            ], actor: $actor);

            return $payment;
        });

        return $this->openCheckout->handle($payment, $actor);
    }

    private function ensureIntentAllowed(Competition $competition, SponsorshipIntent $intent, CarbonImmutable $now): void
    {
        if ($intent === SponsorshipIntent::Publish) {
            if ($competition->status !== CompetitionStatus::Draft) {
                throw $this->invalidState($competition);
            }

            return;
        }

        if (! in_array($competition->status, [CompetitionStatus::Scheduled, CompetitionStatus::Live], true)) {
            throw $this->invalidState($competition);
        }

        if ($competition->invitation_cutoff_at !== null && $now->greaterThanOrEqualTo($competition->invitation_cutoff_at)) {
            throw new ApiException('invitation_cutoff_passed', 'billing.errors.invitation_cutoff_passed', 409);
        }
    }

    private function invalidState(Competition $competition): ApiException
    {
        return new ApiException('invalid_state_transition', status: 409, details: ['status' => $competition->status->value]);
    }
}
