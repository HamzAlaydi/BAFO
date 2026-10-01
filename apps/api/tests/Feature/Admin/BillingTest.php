<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Modules\Admin\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Modules\Admin\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Modules\Admin\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Modules\Admin\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Modules\Admin\Filament\Resources\Payments\Pages\ListPayments;
use App\Modules\Admin\Filament\Resources\Payments\Pages\ViewPayment;
use App\Modules\Admin\Filament\Resources\Plans\Pages\CreatePlan;
use App\Modules\Admin\Filament\Resources\Plans\Pages\EditPlan;
use App\Modules\Admin\Filament\Resources\Plans\Pages\ListPlans;
use App\Modules\Admin\Filament\Resources\Sponsorships\Pages\ListSponsorships;
use App\Modules\Admin\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Admin\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\Gateways\FakePaymentGateway;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 billing resources: plans and coupons CRUD (super admins), vouchers, subscriptions (grant),
 * payments (reconcile, mark paid, refund, CSV), invoices (retry e-invoice, credit note, download)
 * and sponsorships (voucher, pass) — every state change through a Billing Action.
 */

beforeEach(function () {
    Storage::fake('private');
    $this->admin = AdminPanel::signIn();
});

describe('plans', function () {
    it('creates a plan with SAR prices stored in halalas', function () {
        Livewire::test(CreatePlan::class)
            ->fillForm([
                'code' => 'enterprise',
                'sort_order' => 5,
                'name' => ['ar' => 'المؤسسات', 'en' => 'Enterprise'],
                'description' => ['ar' => null, 'en' => null],
                'seats' => 10,
                'monthly_price_minor' => '315.50',
                'annual_price_minor' => '3155',
                'is_active' => true,
                'features' => [['ar' => 'دعم مخصص', 'en' => 'Dedicated support']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::query()->where('code', 'enterprise')->sole();
        expect($plan->monthly_price_minor)->toBe(31_550)
            ->and($plan->annual_price_minor)->toBe(315_500)
            ->and($plan->features)->toBe([['ar' => 'دعم مخصص', 'en' => 'Dedicated support']])
            ->and($plan->description)->toBeNull()
            ->and(AuditLog::query()->where('action', 'plan.created')->value('meta'))->toMatchArray(['record' => 'plan', 'code' => 'enterprise']);
    });

    it('rejects a duplicate code and a malformed price', function () {
        Plan::factory()->create(['code' => 'taken']);

        Livewire::test(CreatePlan::class)
            ->fillForm(['code' => 'taken', 'name' => ['ar' => 'أ', 'en' => 'A'], 'monthly_price_minor' => '12.345', 'sort_order' => 0])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique', 'monthly_price_minor' => 'regex']);
    });

    it('edits prices but never the code, and audits the change', function () {
        $plan = Plan::factory()->create(['code' => 'plus', 'monthly_price_minor' => 90_000]);

        Livewire::test(EditPlan::class, ['record' => $plan->public_id])
            ->assertFormFieldIsDisabled('code')
            ->assertSchemaStateSet(['monthly_price_minor' => '900.00'])
            ->fillForm(['monthly_price_minor' => '950.00'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($plan->refresh()->monthly_price_minor)->toBe(95_000)
            ->and($plan->code)->toBe('plus')
            ->and(AuditLog::query()->where('action', 'plan.updated')->value('changes'))->toHaveKey('monthly_price_minor');
    });

    it('deletes an unused plan and keeps a plan with subscriptions', function () {
        $unused = Plan::factory()->create();
        $used = Plan::factory()->create();
        Subscription::factory()->create(['plan_id' => $used->id]);

        Livewire::test(EditPlan::class, ['record' => $used->public_id])->assertActionHidden('delete');

        Livewire::test(EditPlan::class, ['record' => $unused->public_id])->callAction('delete');

        expect(Plan::query()->whereKey($unused->id)->exists())->toBeFalse()
            ->and(Plan::query()->whereKey($used->id)->exists())->toBeTrue()
            ->and(AuditLog::query()->where('action', 'plan.deleted')->exists())->toBeTrue();
    });

    it('is closed to operators', function () {
        AdminPanel::signIn(AdminPanel::operator());

        Livewire::test(ListPlans::class)->assertForbidden();
    });
});

describe('coupons and vouchers', function () {
    it('creates a percent coupon with an upper-case code', function () {
        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'launch25',
                'applies_to' => 'any',
                'discount_type' => DiscountType::Percent->value,
                'percent_bps' => '12.5',
                'per_organization_limit' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'LAUNCH25')->sole();
        expect($coupon->kind)->toBe(CouponKind::Coupon)
            ->and($coupon->percent_bps)->toBe(1250)
            ->and($coupon->amount_minor)->toBeNull()
            ->and($coupon->created_by_admin_id)->toBe($this->admin->id)
            ->and(AuditLog::query()->where('action', 'coupon.created')->value('subject_type'))->toBe('coupon');
    });

    it('refuses a code that differs from an existing one only by case', function () {
        Coupon::factory()->create(['code' => 'SPRING']);

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'spring', 'applies_to' => 'any', 'discount_type' => DiscountType::Percent->value, 'percent_bps' => '5'])
            ->call('create')
            ->assertHasFormErrors(['code']);
    });

    it('creates a fixed coupon and edits it', function () {
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'FLAT50', 'applies_to' => 'subscription', 'discount_type' => DiscountType::Fixed->value, 'amount_minor' => '50'])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'FLAT50')->sole();
        expect($coupon->amount_minor)->toBe(5_000)->and($coupon->percent_bps)->toBeNull();

        Livewire::test(EditCoupon::class, ['record' => $coupon->public_id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($coupon->refresh()->is_active)->toBeFalse();
    });

    it('lists coupons and vouchers apart, vouchers with their balance', function () {
        $coupon = Coupon::factory()->create();
        $voucher = Coupon::factory()->voucher(Organization::factory()->create(), 60_000)->create(['balance_minor' => 25_000]);

        Livewire::test(ListCoupons::class)->assertCanSeeTableRecords([$coupon])->assertCanNotSeeTableRecords([$voucher]);
        Livewire::test(ListVouchers::class)->assertCanSeeTableRecords([$voucher])->assertCanNotSeeTableRecords([$coupon])
            ->assertSee('250.00');
    });
});

describe('subscriptions', function () {
    it('lists and filters subscriptions and grants one for a chosen organization', function () {
        $trial = Subscription::factory()->trial()->create();
        $expired = Subscription::factory()->expired()->create();
        $organization = Organization::factory()->create();
        $plan = Plan::factory()->create();

        Livewire::test(ListSubscriptions::class)
            ->assertCanSeeTableRecords([$trial, $expired])
            ->filterTable('status', SubscriptionStatus::Expired->value)
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$trial]);

        Livewire::test(ListSubscriptions::class)
            ->callAction(TestAction::make('grantSubscription')->table(), data: [
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'seats' => 2,
                'starts_at' => now('Asia/Riyadh')->toDateTimeString(),
                'ends_at' => now('Asia/Riyadh')->addYear()->toDateTimeString(),
                'reason' => 'Pilot customer',
            ])
            ->assertHasNoActionErrors();

        expect(Subscription::query()->where('organization_id', $organization->id)->value('source'))->toBe(SubscriptionSource::Grant);
    });
});

describe('payments', function () {
    it('lists payments with the review filter', function () {
        $review = Payment::factory()->expired()->create(['metadata' => ['needs_manual_review' => true]]);
        $plain = Payment::factory()->succeeded()->create();

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$review, $plain])
            ->filterTable('needs_review')
            ->assertCanSeeTableRecords([$review])
            ->assertCanNotSeeTableRecords([$plain]);

        $this->get('/admin/payments/'.$review->public_id)->assertOk();
    });

    it('reconciles a payment with the gateway through ReconcilePayment', function () {
        $payment = Payment::factory()->create();
        $payment->forceFill(['metadata' => [...$payment->metadata, FakePaymentGateway::STATE_KEY => 'failed']])->save();

        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('reconcile')->table($payment))
            ->assertNotified();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
    });

    it('marks a payment paid manually, then records a refund', function () {
        $payment = Payment::factory()->create();
        Subscription::factory()->pendingPayment()->create(['organization_id' => $payment->organization_id, 'payment_id' => $payment->id]);

        Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
            ->callAction('markPaid', data: ['reference' => ''])
            ->assertHasActionErrors(['reference' => 'required']);

        Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
            ->callAction('markPaid', data: ['reference' => 'TRX-100'])
            ->assertHasNoActionErrors();

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatus::Succeeded)
            ->and($payment->manual_reference)->toBe('TRX-100')
            ->and($payment->subscription->status)->toBe(SubscriptionStatus::Active);

        Livewire::test(ViewPayment::class, ['record' => $payment->public_id])
            ->assertActionHidden('markPaid')
            ->callAction('recordRefund', data: ['reference' => 'RF-1']);

        expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
            ->and($payment->refunded_by_admin_id)->toBe($this->admin->id);
    });

    it('exports the filtered payments as CSV', function () {
        $succeeded = Payment::factory()->succeeded()->create();
        $failed = Payment::factory()->failed()->create();

        $component = Livewire::test(ListPayments::class)
            ->filterTable('status', [PaymentStatus::Succeeded->value])
            ->callAction(TestAction::make('exportCsv')->table())
            ->assertFileDownloaded();

        $csv = base64_decode((string) data_get($component->effects, 'download.content'));
        expect($csv)->toStartWith("\xEF\xBB\xBF")
            ->and($csv)->toContain($succeeded->public_id)
            ->and($csv)->not->toContain($failed->public_id);
    });
});

describe('invoices', function () {
    it('retries a pending e-invoice through IssueEInvoice', function () {
        $invoice = Invoice::factory()->create();

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords([$invoice])
            ->callAction(TestAction::make('retryEInvoice')->table($invoice))
            ->assertNotified();

        $invoice->refresh();
        expect($invoice->einvoice_status)->toBe(EInvoiceStatus::Cleared)
            ->and($invoice->pdf_file_id)->not->toBeNull();
    });

    it('records a credit note once for a tax invoice', function () {
        $invoice = Invoice::factory()->cleared()->create();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->public_id])
            ->callAction('recordCreditNote', data: ['reference' => 'ZATCA-CN-7'])
            ->assertHasNoActionErrors();

        expect(Invoice::query()->where('original_invoice_id', $invoice->id)->value('type'))->toBe(InvoiceType::CreditNote);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->public_id])->assertActionHidden('recordCreditNote');
    });

    it('downloads the invoice PDF', function () {
        $invoice = Invoice::factory()->cleared()->create();
        $file = app(FileStorage::class)->storeContents('%PDF-1.4 test', $invoice->number.'.pdf', 'application/pdf', FilePurpose::InvoicePdf, $invoice->organization_id);
        $invoice->forceFill(['pdf_file_id' => $file->id])->save();

        Livewire::test(ListInvoices::class)
            ->callAction(TestAction::make('download')->table($invoice))
            ->assertFileDownloaded($invoice->number.'.pdf');
    });
});

describe('sponsorships', function () {
    it('issues a voucher for the unused passes of a settled sponsorship', function () {
        $sponsorship = CompetitionSponsorship::factory()->create([
            'status' => SponsorshipStatus::Settled, 'funded_passes' => 3, 'unused_count' => 2, 'settled_at' => now(),
        ]);

        Livewire::test(ListSponsorships::class)
            ->filterTable('awaiting_voucher')
            ->assertCanSeeTableRecords([$sponsorship])
            ->callAction(TestAction::make('issueVoucher')->table($sponsorship), data: ['reason' => ''])
            ->assertHasNoActionErrors();

        $voucher = Coupon::query()->whereKey($sponsorship->refresh()->voucher_coupon_id)->sole();
        expect($voucher->kind)->toBe(CouponKind::Voucher)
            ->and($voucher->amount_minor)->toBe(40_000);

        Livewire::test(ListSponsorships::class)->assertActionHidden(TestAction::make('issueVoucher')->table($sponsorship));
    });

    it('grants a pass for a chosen invitation', function () {
        $sponsorship = CompetitionSponsorship::factory()->selected()->create();
        $invitation = Invitation::factory()->sent()->create(['competition_id' => $sponsorship->competition_id]);

        Livewire::test(ListSponsorships::class)
            ->callAction(TestAction::make('grantPass')->table($sponsorship), data: ['invitation_id' => $invitation->id])
            ->assertHasNoActionErrors();

        expect(SponsoredPass::query()->where('invitation_id', $invitation->id)->value('source'))->toBe(PassSource::AdminGrant)
            ->and($sponsorship->refresh()->funded_passes)->toBe(1);
    });
});
