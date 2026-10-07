<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Support\Features\ReleaseScope;
use Tests\Support\Billing\Billing;

/*
|--------------------------------------------------------------------------
| Release scope: Billing (RELEASE_SCOPE.md §1.5)
|--------------------------------------------------------------------------
|
| `custom_plan_quote`: GET /plans omits the custom plan and a checkout of it is 404.
| `coupons`: a checkout with a coupon is 404 (the validate route is gated in Platform's test).
| `sponsorship`: PUT …/sponsorship with a mode other than none is 404; mode none and the reads
| stay for existing records.
|
*/

beforeEach(fn () => Billing::plans());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function scopeCheckoutBody(string $plan = 'pro', array $overrides = []): array
{
    return [
        'plan_id' => Plan::query()->where('code', $plan)->value('public_id'),
        'interval' => 'monthly',
        'return_url' => Billing::RETURN_URL,
        ...$overrides,
    ];
}

describe('custom_plan_quote', function () {
    it('omits the custom plan from GET /plans in core only', function () {
        $this->releaseScope(ReleaseScope::Core);
        expect($this->getJson('/api/app/v1/plans')->assertOk()->json('data.*.code'))->toBe(['single', 'plus', 'pro']);

        $this->releaseScope(ReleaseScope::Full);
        expect($this->getJson('/api/app/v1/plans')->assertOk()->json('data.*.code'))->toBe(['single', 'plus', 'pro', 'custom']);
    });

    it('answers 404 feature_disabled for a checkout of the custom plan in core', function () {
        Billing::actingAs(Billing::member());
        $this->releaseScope(ReleaseScope::Core);

        $this->postJson('/api/app/v1/billing/checkout/subscription', scopeCheckoutBody('custom', ['seats' => 6]))
            ->assertNotFound()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('details.feature', 'custom_plan_quote');

        // A fixed plan still checks out in core.
        $this->postJson('/api/app/v1/billing/checkout/subscription', scopeCheckoutBody())->assertCreated();
    });

    it('checks the custom plan out in full', function () {
        Billing::actingAs(Billing::member());

        $this->postJson('/api/app/v1/billing/checkout/subscription', scopeCheckoutBody('custom', ['seats' => 6]))
            ->assertCreated()
            ->assertJsonPath('data.lines.0.kind', 'custom_seats');
    });
});

describe('coupons', function () {
    beforeEach(function () {
        Coupon::factory()->create([
            'code' => 'LAUNCH10', 'discount_type' => DiscountType::Percent, 'percent_bps' => 1000, 'amount_minor' => null,
            'applies_to' => CouponScope::Any, 'organization_id' => null, 'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(),
            'is_active' => true,
        ]);
        Billing::actingAs(Billing::member());
    });

    it('answers 404 feature_disabled for a subscription checkout with a coupon in core', function () {
        $this->releaseScope(ReleaseScope::Core);

        $this->postJson('/api/app/v1/billing/checkout/subscription', scopeCheckoutBody(overrides: ['coupon_code' => 'launch10']))
            ->assertNotFound()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('details.feature', 'coupons');

        // No coupon (null or blank) is the core checkout.
        $this->postJson('/api/app/v1/billing/checkout/subscription', scopeCheckoutBody(overrides: ['coupon_code' => '  ']))
            ->assertCreated()
            ->assertJsonPath('data.coupon', null);
    });

    it('applies the coupon in full', function () {
        $this->postJson('/api/app/v1/billing/checkout/subscription', scopeCheckoutBody(overrides: ['coupon_code' => 'launch10']))
            ->assertCreated()
            ->assertJsonPath('data.coupon.code', 'LAUNCH10');
    });
});

describe('sponsorship', function () {
    it('answers 404 feature_disabled for a covered-fees mode in core', function (string $mode) {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);
        $this->releaseScope(ReleaseScope::Core);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => $mode])
            ->assertNotFound()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('details.feature', 'sponsorship');

        expect(CompetitionSponsorship::query()->count())->toBe(0);
    })->with(['all', 'selected']);

    it('keeps the reads and mode none for a sponsorship configured in full', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Billing::actingAs($owner);
        $this->releaseScope(ReleaseScope::Core);

        $uri = "/api/app/v1/competitions/{$competition->public_id}/sponsorship";

        $this->getJson($uri)->assertOk()->assertJsonPath('data.mode', 'all');
        $this->getJson($uri.'/quote')->assertOk();
        $this->putJson($uri, ['mode' => 'none'])->assertOk()->assertJsonPath('data.mode', 'none');

        expect(CompetitionSponsorship::query()->count())->toBe(0);
    });

    it('still validates the mode', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);
        $this->releaseScope(ReleaseScope::Core);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'everyone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    });

    it('configures covered fees in full', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'selected', 'max_passes' => 3])
            ->assertOk()
            ->assertJsonPath('data.mode', 'selected');
    });
});
