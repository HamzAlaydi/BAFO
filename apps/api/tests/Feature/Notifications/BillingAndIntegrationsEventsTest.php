<?php

declare(strict_types=1);

use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Notifications\ExportFinishedNotification;
use App\Modules\Notifications\Notifications\ImportFinishedNotification;
use App\Modules\Notifications\Notifications\InvoiceIssuedNotification;
use App\Modules\Notifications\Notifications\PaymentFailedNotification;
use App\Modules\Notifications\Notifications\SponsorshipPublishFailedNotification;
use App\Modules\Notifications\Notifications\SponsorshipUnusedPassesNotification;
use App\Modules\Notifications\Notifications\SubscriptionActivatedNotification;
use App\Modules\Notifications\Notifications\SubscriptionExpiredNotification;
use App\Modules\Notifications\Notifications\SubscriptionExpiringNotification;
use App\Modules\Notifications\Notifications\VoucherIssuedNotification;
use App\Modules\Notifications\Notifications\WebhookEndpointDisabledNotification;
use App\Support\Http\Iso;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Notifications\NotificationScenario as S;

/*
 * Billing, Integrations and Identity events (ARCHITECTURE §10) → §11.3. "Billing users" and
 * "integration users" are the owner and admins (billing.view / integrations.manage), never
 * plain members.
 */

beforeEach(function () {
    Notification::fake();

    $this->team = S::team();
});

describe('subscriptions', function () {
    beforeEach(function () {
        $plan = Plan::factory()->create(['name' => ['ar' => 'باقة برو', 'en' => 'Pro']]);
        $this->subscription = Subscription::factory()->create(['organization_id' => $this->team->organization->id, 'plan_id' => $plan->id]);
    });

    it('tells billing users a subscription is active, in-app and by mail', function () {
        S::fire(S::BILLING.'SubscriptionActivated', ['subscription' => $this->subscription]);

        $sent = S::sent(SubscriptionActivatedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->team->managers()))
            ->and($sent[$this->team->owner->id]['channels'])->toBe(['database', 'mail'])
            ->and($sent[$this->team->owner->id]['notification']->params)->toBe([
                'plan_name' => ['ar' => 'باقة برو', 'en' => 'Pro'],
                'ends_at' => Iso::format($this->subscription->ends_at),
            ])
            ->and($sent[$this->team->owner->id]['notification']->route)->toBe('/billing')
            ->and($sent[$this->team->owner->id]['notification']->subject->toArray())->toBe(['type' => 'subscription', 'id' => $this->subscription->public_id]);
        Notification::assertNotSentTo($this->team->member, SubscriptionActivatedNotification::class);
    });

    it('reminds billing users by in-app, push and mail before the end', function () {
        S::fire(S::BILLING.'SubscriptionExpiring', ['subscription' => $this->subscription, 'daysLeft' => 3]);

        $sent = S::sent(SubscriptionExpiringNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->team->managers()))
            ->and($sent[$this->team->admin->id]['channels'])->toBe(['database', 'push', 'mail'])
            ->and($sent[$this->team->admin->id]['notification']->params['days_left'])->toBe(3);
    });

    it('tells billing users the subscription expired', function () {
        S::fire(S::BILLING.'SubscriptionExpired', ['subscription' => $this->subscription]);

        expect(array_keys(S::sent(SubscriptionExpiredNotification::class)))->toBe(S::ids($this->team->managers()));
    });
});

describe('payments, invoices and vouchers', function () {
    it('tells only the paying user that a payment failed', function () {
        $payment = Payment::factory()->failed()->create(['organization_id' => $this->team->organization->id, 'created_by_user_id' => $this->team->member->id]);

        S::fire(S::BILLING.'PaymentFailed', ['payment' => $payment]);

        $sent = S::sent(PaymentFailedNotification::class);
        expect(array_keys($sent))->toBe([$this->team->member->id])
            ->and($sent[$this->team->member->id]['channels'])->toBe(['database', 'mail']);
    });

    it('skips a paying user who is no longer active', function () {
        $this->team->member->update(['status' => UserStatus::Deleted]);
        $payment = Payment::factory()->failed()->create(['organization_id' => $this->team->organization->id, 'created_by_user_id' => $this->team->member->id]);

        S::fire(S::BILLING.'PaymentFailed', ['payment' => $payment]);

        Notification::assertNothingSent();
    });

    it('tells billing users an invoice was issued, linking to it', function () {
        $invoice = Invoice::factory()->create(['organization_id' => $this->team->organization->id]);

        S::fire(S::BILLING.'InvoiceIssued', ['invoice' => $invoice]);

        $sent = S::sent(InvoiceIssuedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->team->managers()))
            ->and($sent[$this->team->owner->id]['notification']->params)->toBe(['number' => $invoice->number])
            ->and($sent[$this->team->owner->id]['notification']->route)->toBe('/billing/invoices/'.$invoice->public_id);
    });

    it('tells billing users of a new voucher with its code and value', function () {
        $voucher = Coupon::factory()->voucher($this->team->organization, 40_000)->create();

        S::fire(S::BILLING.'VoucherIssued', ['voucher' => $voucher]);

        $sent = S::sent(VoucherIssuedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->team->managers()))
            ->and($sent[$this->team->owner->id]['notification']->params)->toBe([
                'code' => $voucher->code,
                'amount_minor' => 40_000,
                'currency' => 'SAR',
            ]);
    });
});

describe('sponsorship', function () {
    beforeEach(function () {
        $competition = S::competition($this->team->organization, fn ($f) => $f->closed());
        $this->sponsorship = CompetitionSponsorship::factory()->funded()->create([
            'competition_id' => $competition->id,
            'organization_id' => $this->team->organization->id,
            'configured_by_user_id' => $this->team->owner->id,
        ]);
    });

    it('tells the issuer billing users about unused passes at settlement', function () {
        $this->sponsorship->update(['status' => 'settled', 'settled_at' => now(), 'unused_count' => 2]);

        S::fire(S::BILLING.'SponsorshipSettled', ['sponsorship' => $this->sponsorship->fresh()]);

        $sent = S::sent(SponsorshipUnusedPassesNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->team->managers()))
            ->and($sent[$this->team->owner->id]['channels'])->toBe(['database', 'mail'])
            ->and($sent[$this->team->owner->id]['notification']->params['unused_count'])->toBe(2);
    });

    it('stays silent when every pass was used', function () {
        $this->sponsorship->update(['status' => 'settled', 'settled_at' => now(), 'unused_count' => 0]);

        S::fire(S::BILLING.'SponsorshipSettled', ['sponsorship' => $this->sponsorship->fresh()]);

        Notification::assertNothingSent();
    });

    it('tells the paying user that the paid publish failed, with the refusal code', function () {
        $payment = Payment::factory()->sponsorship()->succeeded()->create(['organization_id' => $this->team->organization->id, 'created_by_user_id' => $this->team->admin->id]);

        S::fire(S::BILLING.'SponsorshipPublishFailed', ['sponsorship' => $this->sponsorship, 'payment' => $payment, 'errorCode' => 'min_participants_not_met']);

        $sent = S::sent(SponsorshipPublishFailedNotification::class);
        expect(array_keys($sent))->toBe([$this->team->admin->id])
            ->and($sent[$this->team->admin->id]['notification']->params['error_code'])->toBe('min_participants_not_met');
    });
});

describe('integrations', function () {
    it('tells integration users that a webhook endpoint was disabled', function () {
        $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->team->organization->id, 'url' => 'https://erp.example.sa/hooks']);

        S::fire(S::INTEGRATIONS.'WebhookEndpointDisabled', ['endpoint' => $endpoint]);

        $sent = S::sent(WebhookEndpointDisabledNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->team->managers()))
            ->and($sent[$this->team->owner->id]['channels'])->toBe(['database', 'mail'])
            ->and($sent[$this->team->owner->id]['notification']->params)->toBe(['url' => 'https://erp.example.sa/hooks'])
            ->and($sent[$this->team->owner->id]['notification']->route)->toBe('/integrations');
    });

    it('tells the creator in-app that an import finished, with the counts', function () {
        $job = ImportJob::factory()->completed()->create(['organization_id' => $this->team->organization->id, 'created_by_user_id' => $this->team->member->id]);

        S::fire(S::INTEGRATIONS.'ImportFinished', [$job]);

        $sent = S::sent(ImportFinishedNotification::class);
        expect(array_keys($sent))->toBe([$this->team->member->id])
            ->and($sent[$this->team->member->id]['channels'])->toBe(['database'])
            ->and($sent[$this->team->member->id]['notification']->params)->toMatchArray([
                'created' => $job->created_rows,
                'updated' => $job->updated_rows,
                'errors' => $job->error_rows,
            ])
            ->and($sent[$this->team->member->id]['notification']->subject->toArray())->toBe(['type' => 'import_job', 'id' => $job->public_id]);
    });

    it('tells the creator in-app that an export is ready', function () {
        $job = ExportJob::factory()->create(['organization_id' => $this->team->organization->id, 'created_by_user_id' => $this->team->owner->id]);

        S::fire(S::INTEGRATIONS.'ExportFinished', [$job]);

        expect(array_keys(S::sent(ExportFinishedNotification::class)))->toBe([$this->team->owner->id]);
    });
});

describe('account deletion', function () {
    it('removes the device tokens of the deleted user and of every anonymised member', function () {
        $kept = DeviceToken::factory()->create(['user_id' => $this->team->owner->id]);
        $deleted = DeviceToken::factory()->create(['user_id' => $this->team->member->id]);
        $alsoGone = DeviceToken::factory()->create(['user_id' => $this->team->admin->id]);
        $this->team->admin->delete(); // soft-deleted with the organization

        S::fire(S::IDENTITY.'AccountDeleted', [
            'userId' => $this->team->member->id,
            'organizationId' => $this->team->organization->id,
            'scope' => DeletionScope::User,
        ]);

        expect(DeviceToken::query()->pluck('id')->all())->toBe([$kept->id])
            ->and(DeviceToken::query()->whereKey([$deleted->id, $alsoGone->id])->exists())->toBeFalse();
    });
});
