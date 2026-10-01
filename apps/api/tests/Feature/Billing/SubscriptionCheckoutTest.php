<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function billingCheckoutBody(string $plan = 'pro', array $overrides = []): array
{
    return [
        'plan_id' => Plan::query()->where('code', $plan)->value('public_id'),
        'interval' => 'monthly',
        'return_url' => Billing::RETURN_URL,
        ...$overrides,
    ];
}

it('creates a pending fake-gateway checkout for a new subscription', function () {
    $owner = Billing::actingAs(Billing::member());

    $response = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(), ['X-Platform' => 'web'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'purpose', 'status', 'currency', 'lines' => [['kind', 'description', 'quantity', 'unit_price_minor', 'net_minor']],
            'subtotal_minor', 'credit_minor', 'discount_minor', 'vat_rate_bp', 'vat_minor', 'total_minor', 'coupon', 'redirect_url',
            'failure_code', 'failure_message', 'paid_at', 'expires_at', 'created_at', 'invoice_id', 'context' => ['competition_id', 'intent', 'subscription_id']]])
        ->assertJsonPath('data.purpose', 'subscription')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.subtotal_minor', 150_000)
        ->assertJsonPath('data.vat_minor', 22_500)
        ->assertJsonPath('data.total_minor', 172_500)
        ->assertJsonPath('data.lines.0.kind', 'plan')
        ->assertJsonPath('data.lines.0.description', 'باقة برو — شهري');

    $payment = Payment::query()->where('public_id', $response->json('data.id'))->firstOrFail();
    $subscription = Subscription::query()->where('payment_id', $payment->id)->firstOrFail();

    expect($response->json('data.redirect_url'))->toBe(rtrim((string) config('app.url'), '/').'/pay/fake/'.$payment->public_id)
        ->and($payment->gateway)->toBe('fake')
        ->and($payment->gateway_reference)->toBe('fake_'.$payment->public_id)
        ->and($payment->created_by_user_id)->toBe($owner->id)
        ->and($payment->expires_at->diffInMinutes($payment->created_at, true))->toEqualWithDelta(30, 1)
        ->and($subscription->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($subscription->total_minor)->toBe(172_500)
        ->and($response->json('data.context.subscription_id'))->toBe($subscription->public_id);
});

it('prices custom seats per seat', function () {
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('custom', ['seats' => 6, 'interval' => 'annual']))
        ->assertCreated()
        ->assertJsonPath('data.lines.0.kind', 'custom_seats')
        ->assertJsonPath('data.lines.0.quantity', 6)
        ->assertJsonPath('data.lines.0.unit_price_minor', 500_000)
        ->assertJsonPath('data.subtotal_minor', 3_000_000)
        ->assertJsonPath('data.total_minor', 3_450_000);
});

it('applies a coupon at checkout', function () {
    Coupon::factory()->create(['code' => 'SAVE20', 'discount_type' => DiscountType::Percent, 'percent_bps' => 2000, 'amount_minor' => null, 'applies_to' => CouponScope::Any, 'organization_id' => null]);
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('single', ['coupon_code' => 'save20']))
        ->assertCreated()
        ->assertJsonPath('data.subtotal_minor', 31_500)
        ->assertJsonPath('data.discount_minor', 6_300)
        ->assertJsonPath('data.vat_minor', 3_780)
        ->assertJsonPath('data.total_minor', 28_980)
        ->assertJsonPath('data.coupon', ['code' => 'SAVE20']);
});

it('fulfils a zero total at once without the gateway', function () {
    $owner = Billing::actingAs(Billing::member());
    $voucher = Coupon::factory()->voucher($owner->membership->organization, 200_000)->create();

    $response = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('pro', ['coupon_code' => $voucher->code]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.total_minor', 0)
        ->assertJsonPath('data.redirect_url', null);

    $payment = Payment::query()->where('public_id', $response->json('data.id'))->firstOrFail();
    expect($payment->gateway_reference)->toBe('internal:'.$payment->public_id)
        ->and($payment->subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($voucher->refresh()->balance_minor)->toBe(50_000)
        ->and($voucher->redemptions_count)->toBe(1)
        ->and($payment->invoice)->toBeNull();
});

it('returns the same payment for a replayed Idempotency-Key (no double charge)', function () {
    Billing::actingAs(Billing::member());
    $headers = ['Idempotency-Key' => 'checkout-intent-0001'];

    $first = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(), $headers)->assertCreated();
    $second = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(), $headers)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($second->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->first()?->idempotency_key)->toBe('checkout-intent-0001');
});

it('queues a renewal inside the renewal window, starting at the current end', function () {
    $owner = Billing::actingAs(Billing::member());
    $current = Billing::subscribe($owner->membership->organization, 'pro', startsAt: CarbonImmutable::now()->subDays(20), endsAt: CarbonImmutable::now()->addDays(10));

    $response = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody())->assertCreated();
    $payment = Payment::query()->where('public_id', $response->json('data.id'))->firstOrFail();

    expect($payment->metadata['kind'])->toBe('renewal')
        ->and($payment->subscription->replaces_subscription_id)->toBe($current->id)
        ->and($payment->credit_minor)->toBe(0);
});

it('refuses a renewal before the window', function () {
    $owner = Billing::actingAs(Billing::member());
    $current = Billing::subscribe($owner->membership->organization, 'pro', startsAt: CarbonImmutable::now()->subDay(), endsAt: CarbonImmutable::now()->addDays(40));

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody())
        ->assertStatus(409)
        ->assertJsonPath('code', 'subscription_renewal_too_early')
        ->assertJsonPath('details.renewable_from', $current->ends_at->subDays(30)->utc()->format('Y-m-d\TH:i:s.v\Z'));
});

it('upgrades with a pro-rata credit', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-11T00:00:00Z'));
    $owner = Billing::actingAs(Billing::member());
    Billing::subscribe($owner->membership->organization, 'plus', startsAt: CarbonImmutable::parse('2026-10-01T00:00:00Z'), endsAt: CarbonImmutable::parse('2026-10-31T00:00:00Z'));

    // plus: 90000 net; 20 of 30 days left → credit 60000; pro 150000 − 60000 = 90000 + VAT 13500.
    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('pro'))
        ->assertCreated()
        ->assertJsonPath('data.credit_minor', 60_000)
        ->assertJsonPath('data.vat_minor', 13_500)
        ->assertJsonPath('data.total_minor', 103_500);
});

it('refuses a downgrade while a paid plan is current', function () {
    $owner = Billing::actingAs(Billing::member());
    Billing::subscribe($owner->membership->organization, 'pro');

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('single'))
        ->assertStatus(409)
        ->assertJsonPath('code', 'subscription_downgrade_not_allowed');
});

it('treats a purchase during a trial as new', function () {
    $owner = Billing::actingAs(Billing::member());
    Billing::subscribe($owner->membership->organization, 'plus', SubscriptionSource::Trial);

    $response = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('single'))->assertCreated();

    expect(Payment::query()->where('public_id', $response->json('data.id'))->value('metadata')['kind'])->toBe('new');
});

it('refuses purchases from the mobile apps', function (string $platform) {
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(), ['X-Platform' => $platform])
        ->assertForbidden()
        ->assertJsonPath('code', 'purchase_not_available_on_platform');
})->with(['ios', 'android']);

it('requires a complete billing profile', function () {
    $organization = Organization::factory()->incompleteBillingProfile()->create();
    Billing::actingAs(Billing::member($organization));

    $response = $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody())
        ->assertStatus(422)
        ->assertJsonPath('code', 'billing_profile_incomplete');

    expect(array_keys($response->json('errors')))->toContain('legal_name_ar', 'national_address.building_number');
});

it('refuses a return URL outside the allow-list', function (string $url) {
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(overrides: ['return_url' => $url]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'return_url_not_allowed');
})->with(['https://evil.test/return', 'http://localhost.evil.test:3000/ar', 'http://localhost:3001/ar', 'http://user:pw@localhost:3000/ar']);

it('refuses an inactive or unknown plan', function () {
    Billing::actingAs(Billing::member());
    Plan::query()->where('code', 'plus')->update(['is_active' => false]);

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('plus'))
        ->assertStatus(422)->assertJsonPath('code', 'plan_not_available');
    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(overrides: ['plan_id' => '01jaaaaaaaaaaaaaaaaaaaaaaa']))
        ->assertStatus(422)->assertJsonPath('code', 'plan_not_available');
});

it('checks the custom seats', function () {
    Billing::actingAs(Billing::member());
    app(Settings::class)->set('billing.custom_max_seats', 10, null);

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('custom', ['seats' => 11]))
        ->assertStatus(422)->assertJsonPath('code', 'seats_out_of_range');
    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody('custom'))
        ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors(['seats']);
});

it('reports coupon errors at checkout', function () {
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody(overrides: ['coupon_code' => 'NOPE']))
        ->assertStatus(422)->assertJsonPath('code', 'coupon_invalid');
    expect(Payment::query()->count())->toBe(0);
});

it('validates the body', function () {
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', ['interval' => 'weekly', 'return_url' => 'not a url'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id', 'interval', 'return_url']);
});

it('checks billing.purchase before the body', function () {
    Billing::actingAs(Billing::member(role: OrgRole::Member));

    $this->postJson('/api/app/v1/billing/checkout/subscription', [])->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('lets a member with can_purchase check out', function () {
    Billing::actingAs(Billing::member(role: OrgRole::Member, membership: ['can_purchase' => true]));

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody())->assertCreated();
});

it('requires authentication', function () {
    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody())->assertUnauthorized();
});

it('fails the payment when the gateway is not configured', function () {
    config(['bafo.billing.gateway.driver' => 'moyasar', 'bafo.billing.gateway.moyasar.secret_key' => '']);
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', billingCheckoutBody())
        ->assertStatus(503)
        ->assertJsonPath('code', 'gateway_not_configured');

    $payment = Payment::query()->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_code)->toBe('gateway_not_configured')
        ->and($payment->subscription->status)->toBe(SubscriptionStatus::Cancelled);
});
