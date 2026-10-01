<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

/**
 * POST /competitions/{competition}/offers (API.md §1.6).
 *
 * `amount_minor` is deliberately not validated here: ARCHITECTURE §7.4 fixes the order of the
 * checks (permission, Idempotency-Key, visibility, participant, replay, rate limit, then the
 * amount syntax with its own codes `offer_amount_invalid`, `offer_amount_too_large`,
 * `offer_granularity`), so the engine validates it in step 7.
 */
final class SubmitOfferRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'confirm_outlier' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function amount(): mixed
    {
        return $this->input('amount_minor');
    }

    public function confirmOutlier(): bool
    {
        return $this->boolean('confirm_outlier');
    }
}
