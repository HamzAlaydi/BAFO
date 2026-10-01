<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

/**
 * Validated organization profile input (register or `PATCH /organization`), already mapped to
 * `organizations` columns: internal `region_id`, `address_*` for the national address.
 */
final readonly class OrganizationProfileData
{
    /**
     * @param  array<string, mixed>  $attributes  only the columns that were sent
     * @param  list<int>|null  $categoryIds  internal category ids; null = not sent (unchanged)
     */
    public function __construct(
        public array $attributes,
        public ?array $categoryIds,
    ) {}
}
