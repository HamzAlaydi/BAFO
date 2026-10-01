<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Http\Requests\CustomQuoteRequest;
use App\Modules\Billing\Http\Resources\PlanResource;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\BillingSettings;
use App\Modules\Billing\Services\PricingCalculator;
use App\Support\Exceptions\ApiException;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;

/**
 * `GET /plans` and `GET /plans/custom-quote` (guest, API.md §1.7).
 */
final class PlanController extends BillingController
{
    /**
     * Active plans by `sort_order`, the custom plan last.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('is_custom')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->ok(PlanResource::collection($plans));
    }

    public function customQuote(CustomQuoteRequest $request, BillingSettings $settings, PricingCalculator $pricing): JsonResponse
    {
        $seats = $request->seats();
        $min = $settings->customMinSeats();
        $max = $settings->customMaxSeats();

        if ($seats < $min || $seats > $max) {
            throw new ApiException('seats_out_of_range', 'billing.errors.seats_out_of_range', 422,
                replace: ['min' => $min, 'max' => $max],
                details: ['min_seats' => $min, 'max_seats' => $max],
            );
        }

        $unit = $settings->customSeatPrice($request->billingInterval());
        $price = $pricing->breakdown($unit * $seats, 0, null, $settings->vatRateBp());

        return $this->ok([
            'seats' => $seats,
            'interval' => $request->billingInterval()->value,
            'unit_price_minor' => $unit,
            'subtotal_minor' => $price->subtotalMinor,
            'vat_rate_bp' => $price->vatRateBp,
            'vat_minor' => $price->vatMinor,
            'total_minor' => $price->totalMinor,
            'currency' => Money::CURRENCY,
        ]);
    }
}
