<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\BillingSettings;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API.md §2.10 `Plan`. Names, descriptions and features are in the request locale. The custom
 * plan has null seats and prices and adds `custom` (the per-seat prices and seat bounds from
 * the settings).
 *
 * @mixin Plan
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $settings = app(BillingSettings::class);

        $plan = [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->translated('name', $locale),
            'description' => $this->translated('description', $locale),
            'features' => array_map(
                static fn (array $feature): string => $locale === 'en' ? $feature['en'] : $feature['ar'],
                $this->features,
            ),
            'seats' => $this->is_custom ? null : $this->seats,
            'monthly_price_minor' => $this->is_custom ? null : $this->monthly_price_minor,
            'annual_price_minor' => $this->is_custom ? null : $this->annual_price_minor,
            'monthly_list_price_minor' => $this->is_custom ? null : $this->monthly_list_price_minor,
            'annual_list_price_minor' => $this->is_custom ? null : $this->annual_list_price_minor,
            'is_custom' => $this->is_custom,
            'is_featured' => $this->is_featured,
            'currency' => Money::CURRENCY,
            'vat_rate_bp' => $settings->vatRateBp(),
        ];

        if ($this->is_custom) {
            $plan['custom'] = [
                'min_seats' => $settings->customMinSeats(),
                'max_seats' => $settings->customMaxSeats(),
                'seat_monthly_price_minor' => $settings->customSeatPrice(BillingInterval::Monthly),
                'seat_annual_price_minor' => $settings->customSeatPrice(BillingInterval::Annual),
            ];
        }

        return $plan;
    }
}
