<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Imports;

/**
 * The vendor import and export columns (ARCHITECTURE §14.7), in template order.
 */
final class VendorColumns
{
    /**
     * @var list<string>
     */
    public const array ALL = [
        'external_system',
        'external_id',
        'name',
        'name_en',
        'cr_number',
        'vat_number',
        'email',
        'contact_name',
        'phone',
        'region_code',
        'city',
        'category_codes',
        'status',
    ];

    /**
     * @var list<string>
     */
    public const array REQUIRED = ['name', 'email'];

    /** Separator of `category_codes`. */
    public const string LIST_SEPARATOR = ';';
}
