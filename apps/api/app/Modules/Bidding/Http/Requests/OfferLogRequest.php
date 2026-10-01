<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

/**
 * GET /competitions/{competition}/offers/log (API.md §0.5 sequence pagination):
 * `after_seq` (default 0) and `limit` (default 200, max 500).
 */
final class OfferLogRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'after_seq' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function afterSeq(): int
    {
        return (int) ($this->validated('after_seq') ?? 0);
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 200);
    }
}
