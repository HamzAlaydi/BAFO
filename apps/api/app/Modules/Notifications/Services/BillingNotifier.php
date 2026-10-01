<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Notifications\Data\Delivery;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationRoutes;
use App\Support\Http\Iso;
use App\Support\Money\Money;

/**
 * Billing notifications (ARCHITECTURE §11.3, rows `subscription.*`, `payment.failed`,
 * `invoice.issued`, `sponsorship.*`, `voucher.issued`). "Billing users" are the active members
 * with `billing.view`. Billing mails may show the organization's own amounts (§11.1).
 */
final readonly class BillingNotifier
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private Recipients $recipients,
    ) {}

    /** `payment.failed`: the paying user. */
    public function paymentFailed(Payment $payment): int
    {
        $payer = $this->recipients->user($payment->created_by_user_id);

        return $this->dispatcher->send(
            NotificationType::PaymentFailed,
            new NotificationPayload([], NotificationSubject::of($payment), NotificationRoutes::BILLING),
            $payer === null ? [] : [new Delivery($payer)],
        );
    }

    /** `subscription.activated`: billing users, with the plan and the end date. */
    public function subscriptionActivated(Subscription $subscription): int
    {
        return $this->toBillingUsers($subscription->organization_id, NotificationType::SubscriptionActivated,
            $this->subscriptionPayload($subscription));
    }

    /** `subscription.expiring` (T−7, T−3, T−1; Billing sends each once): billing users. */
    public function subscriptionExpiring(Subscription $subscription, int $daysLeft): int
    {
        return $this->toBillingUsers($subscription->organization_id, NotificationType::SubscriptionExpiring,
            $this->subscriptionPayload($subscription, ['days_left' => $daysLeft]));
    }

    /** `subscription.expired`: billing users. */
    public function subscriptionExpired(Subscription $subscription): int
    {
        return $this->toBillingUsers($subscription->organization_id, NotificationType::SubscriptionExpired,
            $this->subscriptionPayload($subscription));
    }

    /** `invoice.issued`: billing users; the PDF is downloaded from the dashboard. */
    public function invoiceIssued(Invoice $invoice): int
    {
        return $this->toBillingUsers($invoice->organization_id, NotificationType::InvoiceIssued, new NotificationPayload(
            ['number' => $invoice->number],
            NotificationSubject::of($invoice),
            NotificationRoutes::invoice($invoice),
        ));
    }

    /**
     * `sponsorship.unused_passes`: the issuer's billing users, when passes were left unused at
     * settlement (the platform team then issues a voucher by hand, D15).
     */
    public function sponsorshipSettled(CompetitionSponsorship $sponsorship): int
    {
        if (($sponsorship->unused_count ?? 0) <= 0) {
            return 0;
        }

        $competition = $sponsorship->competition;

        return $this->toBillingUsers($sponsorship->organization_id, NotificationType::SponsorshipUnusedPasses, new NotificationPayload(
            [...CompetitionAudience::params($competition), 'unused_count' => (int) $sponsorship->unused_count],
            NotificationSubject::of($competition),
            NotificationRoutes::competition($competition),
        ));
    }

    /** `sponsorship.publish_failed`: the paying user; payment received, publish refused (`error_code`). */
    public function sponsorshipPublishFailed(CompetitionSponsorship $sponsorship, Payment $payment, string $errorCode): int
    {
        $competition = $sponsorship->competition;
        $payer = $this->recipients->user($payment->created_by_user_id);

        return $this->dispatcher->send(
            NotificationType::SponsorshipPublishFailed,
            new NotificationPayload(
                [...CompetitionAudience::params($competition), 'error_code' => $errorCode],
                NotificationSubject::of($competition),
                NotificationRoutes::competition($competition),
            ),
            $payer === null ? [] : [new Delivery($payer)],
        );
    }

    /** `voucher.issued`: billing users of the voucher's organization, with the code and value. */
    public function voucherIssued(Coupon $voucher): int
    {
        if ($voucher->organization_id === null) {
            return 0;
        }

        return $this->toBillingUsers($voucher->organization_id, NotificationType::VoucherIssued, new NotificationPayload(
            [
                'code' => $voucher->code,
                'amount_minor' => (int) ($voucher->amount_minor ?? $voucher->balance_minor ?? 0),
                'currency' => Money::CURRENCY,
            ],
            NotificationSubject::of($voucher),
            NotificationRoutes::BILLING,
        ));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function subscriptionPayload(Subscription $subscription, array $params = []): NotificationPayload
    {
        return new NotificationPayload(
            [
                'plan_name' => $subscription->plan->name,
                'ends_at' => Iso::format($subscription->ends_at),
                ...$params,
            ],
            NotificationSubject::of($subscription),
            NotificationRoutes::BILLING,
        );
    }

    private function toBillingUsers(int $organizationId, NotificationType $type, NotificationPayload $payload): int
    {
        return $this->dispatcher->send(
            $type,
            $payload,
            Delivery::toEach($this->recipients->membersWith($organizationId, Permission::BillingView)),
        );
    }
}
