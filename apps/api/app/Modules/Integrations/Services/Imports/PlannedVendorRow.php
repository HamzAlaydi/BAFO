<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Imports;

use App\Modules\Integrations\Models\Vendor;

/**
 * A valid import row: its ERP key, the vendor attributes it sets, its categories (null when the
 * file has no `category_codes` column) and the existing vendor it updates (null = create).
 */
final class PlannedVendorRow
{
    public ?Vendor $target = null;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $categoryIds
     */
    public function __construct(
        public readonly ?string $externalSystem,
        public readonly ?string $externalId,
        public readonly array $attributes,
        public readonly ?array $categoryIds,
    ) {}
}
