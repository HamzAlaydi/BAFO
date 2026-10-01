<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

/**
 * `GET /competitions/{competition}/sponsorship/quote?coupon_code=` (API.md §1.7).
 */
final class SponsorshipQuoteRequest extends BillingFormRequest
{
    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function couponCode(): ?string
    {
        return $this->stringOrNull('coupon_code');
    }
}
