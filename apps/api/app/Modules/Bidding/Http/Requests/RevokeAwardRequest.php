<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

/**
 * POST /competitions/{competition}/award/revoke (API.md §1.6): `reason`, 5–1000 characters.
 */
final class RevokeAwardRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
