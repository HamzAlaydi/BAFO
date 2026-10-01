<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\HandleGatewayResult;
use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Billing\Events\PaymentSucceeded;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\EInvoicing\ZatcaQr;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Billing\Billing;

beforeEach(function () {
    Storage::fake('private');
    Billing::plans();
});

/**
 * A pending subscription checkout of the signed-in owner.
 */
function billingFakeCheckout(string $plan = 'pro', array $overrides = []): Payment
{
    $response = test()->postJson('/api/app/v1/billing/checkout/subscription', [
        'plan_id' => Plan::query()->where('code', $plan)->value('public_id'),
        'interval' => 'monthly',
        'return_url' => Billing::RETURN_URL,
        ...$overrides,
    ])->assertCreated();

    return Payment::query()->where('public_id', $response->json('data.id'))->firstOrFail();
}

it('runs checkout end to end: hosted page, approve, return, poll, invoice', function () {
    $owner = Billing::actingAs(Billing::member());
    $payment = billingFakeCheckout();

    // The hosted page (Arabic by default, the payer's locale).
    $this->get('/pay/fake/'.$payment->public_id)
        ->assertOk()
        ->assertSee('BAFO')
        ->assertSee('اعتماد الدفع')
        ->assertSee('1,725.00 ر.س')
        ->assertSee('باقة برو — شهري');
    $this->get('/pay/fake/'.$payment->public_id.'?lang=en')->assertOk()->assertSee('Approve payment')->assertSee('SAR 1,725.00');

    // Approve → 303 back to the web return page with ?payment=.
    $this->post('/pay/fake/'.$payment->public_id.'/approve')
        ->assertStatus(303)
        ->assertRedirect(Billing::RETURN_URL.'?payment='.$payment->public_id);

    $payment->refresh();
    $subscription = Subscription::query()->where('payment_id', $payment->id)->firstOrFail();
    $invoice = Invoice::query()->where('payment_id', $payment->id)->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->starts_at?->equalTo($payment->paid_at))->toBeTrue()
        ->and($subscription->ends_at?->equalTo($payment->paid_at->addMonthNoOverflow()))->toBeTrue()
        ->and($invoice->number)->toMatch('/^BAFO-INV-\d{4}-\d{6}$/')
        ->and($invoice->einvoice_status)->toBe(EInvoiceStatus::Cleared)
        ->and($invoice->einvoice_document_id)->toBe('fake-'.$invoice->public_id)
        ->and($invoice->zatca_uuid)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($invoice->total_minor)->toBe(172_500)
        ->and($invoice->vat_minor)->toBe(22_500)
        ->and($invoice->lines()->count())->toBe(1)
        ->and($invoice->buyer_snapshot['cr_number'])->toBe($owner->membership->organization->cr_number)
        ->and($invoice->pdf_file_id)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'subscription.activated')->where('organization_id', $payment->organization_id)->exists())->toBeTrue();

    $qr = ZatcaQr::decode((string) $invoice->qr_payload);
    expect($qr[1])->toBe(config('bafo.billing.seller.name_ar'))
        ->and($qr[2])->toBe(config('bafo.billing.seller.vat_number'))
        ->and($qr[4])->toBe('1725.00')
        ->and($qr[5])->toBe('225.00');

    // The web page polls, then verifies once (idempotent).
    $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.invoice_id', $invoice->public_id);
    $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded');

    // The invoice and its PDF.
    $this->getJson('/api/app/v1/billing/invoices/'.$invoice->public_id)
        ->assertOk()
        ->assertJsonPath('data.pdf.available', true)
        ->assertJsonPath('data.einvoice_status', 'cleared');
    $pdf = $this->get('/api/app/v1/billing/invoices/'.$invoice->public_id.'/pdf')->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(substr((string) $pdf->streamedContent(), 0, 4))->toBe('%PDF');

    // The shared file endpoint follows the invoice_pdf rule.
    $this->get('/api/app/v1/files/'.$invoice->pdfFile?->public_id.'/download')->assertOk();

    expect(Subscription::query()->where('status', SubscriptionStatus::Active->value)->count())->toBe(1);
});

it('dispatches the payment, subscription and invoice events', function () {
    Event::fake([PaymentSucceeded::class, SubscriptionActivated::class, InvoiceIssued::class]);
    Billing::actingAs(Billing::member());
    $payment = billingFakeCheckout();

    $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    Event::assertDispatched(SubscriptionActivated::class);
});

it('fails the payment on decline and cancels the pending subscription', function () {
    Event::fake([PaymentFailed::class]);
    Billing::actingAs(Billing::member());
    $payment = billingFakeCheckout();

    $this->post('/pay/fake/'.$payment->public_id.'/decline')
        ->assertStatus(303)
        ->assertRedirect(Billing::RETURN_URL.'?payment='.$payment->public_id);

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_code)->toBe('declined')
        ->and($payment->subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(Invoice::query()->count())->toBe(0);

    Event::assertDispatched(PaymentFailed::class, fn (PaymentFailed $e) => $e->payment->is($payment));

    $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_code', 'declined');
});

it('answers 409 with the page once the payment is settled', function () {
    Billing::actingAs(Billing::member());
    $payment = billingFakeCheckout();

    $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);
    $this->post('/pay/fake/'.$payment->public_id.'/decline')->assertStatus(409)->assertSee('تمت معالجة هذه الدفعة مسبقاً');
    $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(409);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('fulfils exactly once however often the result arrives (no double charge)', function () {
    $owner = Billing::actingAs(Billing::member());
    $organization = $owner->membership->organization;
    $coupon = Coupon::factory()->voucher($organization, 50_000)->create();
    $payment = billingFakeCheckout('pro', ['coupon_code' => $coupon->code]);
    $state = GatewayPaymentState::paid($payment->total_minor, 'SAR');
    $handle = app(HandleGatewayResult::class);

    $handle->handle($payment, $state, Actor::system());
    $handle->handle($payment, $state, Actor::system());
    $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')->assertOk();

    expect(Subscription::query()->where('organization_id', $organization->id)->where('status', SubscriptionStatus::Active->value)->count())->toBe(1)
        ->and(CouponRedemption::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and($coupon->refresh()->balance_minor)->toBe(0)
        ->and(Invoice::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payment.succeeded')->count())->toBe(1);
});

it('fails a payment whose paid amount differs', function () {
    Billing::actingAs(Billing::member());
    $payment = billingFakeCheckout();

    app(HandleGatewayResult::class)->handle($payment, GatewayPaymentState::paid(100, 'SAR'), Actor::system());

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_code)->toBe('amount_mismatch')
        ->and($payment->subscription->status)->toBe(SubscriptionStatus::Cancelled);
});

it('supersedes a trial when the paid plan activates', function () {
    $owner = Billing::actingAs(Billing::member());
    $trial = Billing::subscribe($owner->membership->organization, 'plus', SubscriptionSource::Trial);
    $payment = billingFakeCheckout('single');

    $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);

    expect($trial->refresh()->status)->toBe(SubscriptionStatus::Superseded)
        ->and($trial->superseded_at)->not->toBeNull()
        ->and($payment->refresh()->subscription->status)->toBe(SubscriptionStatus::Active);
});

it('queues an approved renewal after the current period', function () {
    $owner = Billing::actingAs(Billing::member());
    $current = Billing::subscribe($owner->membership->organization, 'pro', startsAt: CarbonImmutable::now()->subDays(25), endsAt: CarbonImmutable::now()->addDays(5));
    $payment = billingFakeCheckout('pro');

    $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);
    $renewal = $payment->refresh()->subscription;

    expect($current->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($renewal->starts_at?->equalTo($current->ends_at))->toBeTrue()
        ->and($renewal->replaces_subscription_id)->toBe($current->id);

    $this->getJson('/api/app/v1/billing/subscription')
        ->assertJsonPath('data.current.id', $current->public_id)
        ->assertJsonPath('data.upcoming.id', $renewal->public_id);
});

it('supersedes the current plan on an upgrade', function () {
    $owner = Billing::actingAs(Billing::member());
    $current = Billing::subscribe($owner->membership->organization, 'plus');
    $payment = billingFakeCheckout('pro');

    $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);

    expect($current->refresh()->status)->toBe(SubscriptionStatus::Superseded)
        ->and($payment->refresh()->subscription->replaces_subscription_id)->toBe($current->id);
});

it('does not serve the page for unknown or internal payments', function () {
    $this->get('/pay/fake/01jaaaaaaaaaaaaaaaaaaaaaaa')->assertNotFound();
});
