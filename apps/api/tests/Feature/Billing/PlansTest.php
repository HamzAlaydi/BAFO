<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Plan;
use App\Support\Settings\Settings;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

it('lists the active plans for guests, custom last, in the request locale', function () {
    Plan::query()->where('code', 'plus')->update(['is_active' => false]);

    $response = $this->getJson('/api/app/v1/plans', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'description', 'features', 'seats', 'monthly_price_minor',
            'annual_price_minor', 'monthly_list_price_minor', 'annual_list_price_minor', 'is_custom', 'is_featured', 'currency', 'vat_rate_bp']],
            'meta' => ['server_time']]);

    expect($response->json('data.*.code'))->toBe(['single', 'pro', 'custom'])
        ->and($response->json('data.1'))->toMatchArray([
            'name' => 'Pro', 'seats' => 3, 'monthly_price_minor' => 150_000, 'annual_price_minor' => 1_500_000,
            'monthly_list_price_minor' => 300_000, 'is_featured' => true, 'currency' => 'SAR', 'vat_rate_bp' => 1500,
        ])
        ->and($response->json('data.1.features.0'))->toBe('Three users')
        ->and($response->json('data.2'))->toMatchArray(['seats' => null, 'monthly_price_minor' => null, 'is_custom' => true])
        ->and($response->json('data.2.custom'))->toBe([
            'min_seats' => 4, 'max_seats' => 50, 'seat_monthly_price_minor' => 50_000, 'seat_annual_price_minor' => 500_000,
        ])
        ->and($response->json('data.0'))->not->toHaveKey('custom');

    expect($this->getJson('/api/app/v1/plans')->json('data.0.name'))->toBe('باقة فردية');
});

it('quotes custom seats: happy path', function () {
    app(Settings::class)->set('billing.custom_seat_annual_price_minor', 480_000, null);

    $this->getJson('/api/app/v1/plans/custom-quote?seats=10&interval=annual')
        ->assertOk()
        ->assertJsonPath('data', [
            'seats' => 10, 'interval' => 'annual', 'unit_price_minor' => 480_000, 'subtotal_minor' => 4_800_000,
            'vat_rate_bp' => 1500, 'vat_minor' => 720_000, 'total_minor' => 5_520_000, 'currency' => 'SAR',
        ]);
});

it('rejects seats outside the custom bounds', function (int $seats) {
    $this->getJson("/api/app/v1/plans/custom-quote?seats={$seats}&interval=monthly")
        ->assertStatus(422)
        ->assertJsonPath('code', 'seats_out_of_range')
        ->assertJsonPath('details', ['min_seats' => 4, 'max_seats' => 50]);
})->with([3, 51]);

it('validates the custom quote query', function () {
    $this->getJson('/api/app/v1/plans/custom-quote?seats=abc&interval=weekly')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['seats', 'interval']);
});
