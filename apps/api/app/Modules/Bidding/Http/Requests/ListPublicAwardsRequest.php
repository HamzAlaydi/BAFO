<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\ErpSyncStatus;
use Carbon\CarbonImmutable;

/**
 * Public GET /awards (API.md §3.5): the polling endpoint for ERPs. Filters `status`,
 * `erp_sync_status`, `competition_id`, `updated_since`, `external_system` + `external_id`
 * (the competition's refs); cursor pagination (`per_page` default 50, max 200).
 */
final class ListPublicAwardsRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $awardStatuses = implode(',', array_map(static fn (AwardStatus $s): string => $s->value, AwardStatus::cases()));
        $syncStatuses = implode(',', array_map(static fn (ErpSyncStatus $s): string => $s->value, ErpSyncStatus::cases()));

        return [
            'status' => ['nullable', 'string', 'in:'.$awardStatuses],
            'erp_sync_status' => ['nullable', 'string', 'in:'.$syncStatuses],
            'competition_id' => ['nullable', 'string', 'max:26'],
            'updated_since' => ['nullable', 'date'],
            'external_system' => ['nullable', 'string', 'max:60', 'required_with:external_id'],
            'external_id' => ['nullable', 'string', 'max:120', 'required_with:external_system'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function filter(string $key): ?string
    {
        return $this->stringOrNull($key);
    }

    public function updatedSince(): ?CarbonImmutable
    {
        $value = $this->stringOrNull('updated_since');

        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 50);
    }
}
