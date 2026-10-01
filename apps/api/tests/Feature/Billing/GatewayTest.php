<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\Gateways\MoyasarPaymentGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Billing\Billing;

beforeEach(function () {
    Storage::fake('private');
    Http::preventStrayRequests();
    config([
        'bafo.billing.gateway.moyasar.base_url' => 'https://moyasar.test',
        'bafo.billing.gateway.moyasar.secret_key' => 'sk_test_placeholder',
        'bafo.billing.gateway.moyasar.webhook_secret' => 'whsec_placeholder',
    ]);
});

function billingMoyasarPayment(array $attributes = []): Payment
{
    return Payment::factory()->create([
        'gateway' => 'moyasar',
        'gateway_reference' => 'inv_123',
        ...$attributes,
    ]);
}

describe('POST /billing/gateway-webhooks/{gateway}', function () {
    it('re-reads a verified Moyasar payment and fulfils it', function () {
        Billing::plans();
        $payment = billingMoyasarPayment();
        Http::fake(['moyasar.test/v1/invoices/inv_123' => Http::response(['id' => 'inv_123', 'status' => 'paid', 'amount' => $payment->total_minor, 'currency' => 'SAR'])]);

        $this->postJson('/api/app/v1/billing/gateway-webhooks/moyasar', [
            'secret_token' => 'whsec_placeholder',
            'type' => 'payment_paid',
            'data' => ['id' => 'pay_1', 'invoice_id' => 'inv_123', 'status' => 'paid'],
        ])->assertOk()->assertJsonPath('data', []);

        expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded);
    });

    it('answers 200 for an unknown payment', function () {
        $this->postJson('/api/app/v1/billing/gateway-webhooks/moyasar', [
            'secret_token' => 'whsec_placeholder', 'data' => ['invoice_id' => 'inv_unknown'],
        ])->assertOk();
    });

    it('rejects a bad signature with 400 invalid_webhook', function (array $body) {
        $payment = billingMoyasarPayment();

        $this->postJson('/api/app/v1/billing/gateway-webhooks/moyasar', $body)
            ->assertStatus(400)
            ->assertJsonPath('code', 'invalid_webhook');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    })->with([
        'wrong secret' => [['secret_token' => 'nope', 'data' => ['invoice_id' => 'inv_123']]],
        'no secret' => [['data' => ['invoice_id' => 'inv_123']]],
    ]);

    it('has no webhooks on the fake gateway', function () {
        $this->postJson('/api/app/v1/billing/gateway-webhooks/fake', [])->assertStatus(400)->assertJsonPath('code', 'invalid_webhook');
    });

    it('answers 404 for an unknown gateway', function () {
        $this->postJson('/api/app/v1/billing/gateway-webhooks/paypal', [])->assertNotFound();
    });
});

describe('the moyasar driver', function () {
    it('creates a hosted invoice with Basic auth', function () {
        Http::fake(['moyasar.test/v1/invoices' => Http::response(['id' => 'inv_999', 'url' => 'https://checkout.moyasar.test/inv_999'], 201)]);
        $payment = Payment::factory()->create(['gateway' => 'moyasar']);

        $session = app(MoyasarPaymentGateway::class)->createCheckout($payment, Billing::RETURN_URL);

        expect($session->reference)->toBe('inv_999')
            ->and($session->redirectUrl)->toBe('https://checkout.moyasar.test/inv_999');
        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
            && $request['amount'] === $payment->total_minor
            && $request['currency'] === 'SAR'
            && $request['callback_url'] === Billing::RETURN_URL
            && $request['metadata'] === ['payment_id' => $payment->public_id]
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_placeholder:')));
    });

    it('maps the invoice status', function (string $status, PaymentStatus $expected) {
        Billing::plans();
        $payment = billingMoyasarPayment();
        Http::fake(['moyasar.test/*' => Http::response(['id' => 'inv_123', 'status' => $status, 'amount' => $payment->total_minor, 'currency' => 'SAR'])]);
        Billing::actingAs(Billing::member($payment->organization));

        $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', $expected->value);
    })->with([
        ['paid', PaymentStatus::Succeeded],
        ['failed', PaymentStatus::Failed],
        ['expired', PaymentStatus::Failed],
        ['initiated', PaymentStatus::Pending],
    ]);

    it('answers 502 gateway_error when Moyasar fails', function () {
        Http::fake(['moyasar.test/*' => Http::response(['message' => 'boom'], 500)]);
        $payment = billingMoyasarPayment();
        Billing::actingAs(Billing::member($payment->organization));

        $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')
            ->assertStatus(502)
            ->assertJsonPath('code', 'gateway_error');
    });

    it('answers 503 gateway_not_configured without keys', function () {
        config(['bafo.billing.gateway.moyasar.secret_key' => '']);
        $payment = billingMoyasarPayment();
        Billing::actingAs(Billing::member($payment->organization));

        $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')
            ->assertStatus(503)
            ->assertJsonPath('code', 'gateway_not_configured');
    });
});
