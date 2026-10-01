<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A pending `fake`-gateway checkout of a monthly `pro`-priced subscription: SAR 1,500 + 15% VAT
 * = SAR 1,725 (§13.2 math), held for 30 minutes.
 *
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = 150_000;
        $vat = Money::vat($subtotal);

        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => static fn (array $attributes): int => User::factory()
                ->withMembership(Organization::query()->findOrFail($attributes['organization_id']))
                ->create()
                ->id,
            'purpose' => PaymentPurpose::Subscription,
            'status' => PaymentStatus::Pending,
            'gateway' => 'fake',
            'gateway_reference' => null,
            'currency' => 'SAR',
            'subtotal_minor' => $subtotal,
            'discount_minor' => 0,
            'credit_minor' => 0,
            'vat_rate_bp' => 1500,
            'vat_minor' => $vat,
            'total_minor' => $subtotal + $vat,
            'coupon_id' => null,
            'idempotency_key' => null,
            'return_url' => 'http://localhost:3000/ar/dashboard/billing/checkout/return',
            'redirect_url' => null,
            'metadata' => ['plan_code' => 'pro', 'interval' => 'monthly'],
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function succeeded(): self
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Succeeded,
            'gateway_reference' => 'fake_'.Str::lower((string) Str::ulid()),
            'paid_at' => now(),
        ]);
    }

    public function failed(string $code = 'declined'): self
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
            'failure_code' => $code,
            'failure_message' => 'تم رفض عملية الدفع',
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * A checkout for sponsored participation passes (two passes at SAR 200 each).
     */
    public function sponsorship(int $passes = 2, int $unitPriceMinor = 20_000): self
    {
        $subtotal = $passes * $unitPriceMinor;
        $vat = Money::vat($subtotal);

        return $this->state([
            'purpose' => PaymentPurpose::Sponsorship,
            'subtotal_minor' => $subtotal,
            'vat_minor' => $vat,
            'total_minor' => $subtotal + $vat,
            'metadata' => ['intent' => 'publish'],
        ]);
    }
}
