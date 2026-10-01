<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\GrantSubscription;
use App\Modules\Billing\Actions\InvoicePayment;
use App\Modules\Billing\Actions\MarkPaymentPaid;
use App\Modules\Billing\Actions\RecordCreditNote;
use App\Modules\Billing\Actions\RecordRefund;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Database\Seeders\BillingDemoSeeder;
use App\Modules\Billing\Database\Seeders\BillingReferenceSeeder;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\Billing\Billing;

function billingAdminActor(): Actor
{
    return new Actor(ActorType::Admin, 7, null, null, null, 7, Channel::Admin, label: 'Admin');
}

describe('seeders', function () {
    it('seeds the four plans idempotently without overwriting admin edits', function () {
        (new BillingReferenceSeeder)->run();
        Plan::query()->where('code', 'pro')->update(['monthly_price_minor' => 140_000]);
        (new BillingReferenceSeeder)->run();

        expect(Plan::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['single', 'plus', 'pro', 'custom'])
            ->and(Plan::query()->where('code', 'single')->value('monthly_price_minor'))->toBe(31_500)
            ->and(Plan::query()->where('code', 'single')->value('annual_list_price_minor'))->toBe(1_500_000)
            ->and(Plan::query()->where('code', 'pro')->value('monthly_price_minor'))->toBe(140_000)
            ->and(Plan::query()->where('code', 'custom')->value('seats'))->toBeNull();
    });

    it('gives the demo organizations their plans through the real flows', function () {
        Storage::fake('private');

        foreach (DemoSeeder::ORGANIZATIONS as $key => $definition) {
            $organization = Organization::factory()->create(['name' => $definition['name'], 'cr_number' => $definition['cr_number']]);
            User::factory()->withMembership($organization, OrgRole::Owner)->create(['email' => $definition['users']['owner']['email']]);
        }

        $this->seed(BillingDemoSeeder::class);

        $planOf = fn (string $key): ?string => Subscription::query()
            ->whereHas('organization', fn ($q) => $q->where('cr_number', DemoSeeder::ORGANIZATIONS[$key]['cr_number']))
            ->where('status', SubscriptionStatus::Active->value)
            ->first()?->plan->code;

        expect($planOf('issuer'))->toBe('pro')
            ->and($planOf('supplier_a'))->toBe('single')
            ->and($planOf('supplier_b'))->toBeNull()
            ->and($planOf('supplier_c'))->toBe('plus')
            ->and($planOf('buyer_d'))->toBe('plus')
            ->and(Subscription::query()->where('source', SubscriptionSource::Trial->value)->count())->toBe(1)
            ->and(Payment::query()->where('status', PaymentStatus::Succeeded->value)->count())->toBe(3)
            ->and(Invoice::query()->count())->toBe(3)
            ->and(Coupon::query()->where('code', BillingDemoSeeder::DEMO_COUPON)->exists())->toBeTrue();
    });

    it('skips organizations Identity has not seeded', function () {
        $this->seed(BillingDemoSeeder::class);

        expect(Subscription::query()->count())->toBe(0);
    });
});

describe('admin actions', function () {
    beforeEach(fn () => Billing::plans());

    it('grants a subscription that supersedes the current one', function () {
        $organization = Organization::factory()->create();
        $current = Billing::subscribe($organization, 'single');

        $grant = app(GrantSubscription::class)->handle(
            $organization, Billing::plan('pro'), 5, CarbonImmutable::now(), CarbonImmutable::now()->addMonths(3), 'Partner programme', billingAdminActor(),
        );

        expect($grant->source)->toBe(SubscriptionSource::Grant)
            ->and($grant->seats)->toBe(5)
            ->and($grant->granted_by_admin_id)->toBe(7)
            ->and($grant->grant_reason)->toBe('Partner programme')
            ->and($current->refresh()->status)->toBe(SubscriptionStatus::Superseded)
            ->and(app(AccessPolicy::class)->seatLimit($organization))->toBe(5);
    });

    it('validates a grant', function () {
        expect(fn () => app(GrantSubscription::class)->handle(
            Organization::factory()->create(), Billing::plan('pro'), 0, CarbonImmutable::now(), CarbonImmutable::now()->subDay(), ' ', billingAdminActor(),
        ))->toThrow(ValidationException::class);
    });

    it('marks a payment paid manually and records a refund', function () {
        Storage::fake('private');
        $payment = Payment::factory()->create();
        Subscription::factory()->pendingPayment()->create(['organization_id' => $payment->organization_id, 'payment_id' => $payment->id]);

        $paid = app(MarkPaymentPaid::class)->handle($payment, 'TRX-123', billingAdminActor());

        expect($paid->status)->toBe(PaymentStatus::Succeeded)
            ->and($paid->manual_reference)->toBe('TRX-123')
            ->and($paid->subscription->status)->toBe(SubscriptionStatus::Active);

        $refunded = app(RecordRefund::class)->handle($paid, 'RF-9', billingAdminActor());

        expect($refunded->status)->toBe(PaymentStatus::Refunded)
            ->and($refunded->refund_reference)->toBe('RF-9')
            ->and($refunded->refunded_by_admin_id)->toBe(7);

        expect(fn () => app(RecordRefund::class)->handle($refunded, 'RF-10', billingAdminActor()))->toThrow(ApiException::class)
            ->and(fn () => app(MarkPaymentPaid::class)->handle($refunded, 'X', billingAdminActor()))->toThrow(ApiException::class);
    });

    it('records a full credit note once per tax invoice', function () {
        Storage::fake('private');
        $payment = Payment::factory()->succeeded()->create();
        $payment->lines()->create([
            'kind' => 'plan', 'description' => ['ar' => 'باقة برو — شهري', 'en' => 'Pro plan, monthly'],
            'quantity' => 1, 'unit_price_minor' => 150_000, 'net_minor' => 150_000,
        ]);
        $invoice = app(InvoicePayment::class)->handle($payment, Actor::system()) ?? throw new RuntimeException('no invoice');

        $note = app(RecordCreditNote::class)->handle($invoice, 'ZATCA-CN-1', billingAdminActor());

        expect($note->type)->toBe(InvoiceType::CreditNote)
            ->and($note->original_invoice_id)->toBe($invoice->id)
            ->and($note->number)->not->toBe($invoice->number)
            ->and($note->total_minor)->toBe($invoice->total_minor)
            ->and($note->einvoice_document_id)->toBe('ZATCA-CN-1')
            ->and($note->lines()->count())->toBe(1);

        expect(fn () => app(RecordCreditNote::class)->handle($invoice, 'ZATCA-CN-2', billingAdminActor()))->toThrow(ApiException::class);
    });
});
