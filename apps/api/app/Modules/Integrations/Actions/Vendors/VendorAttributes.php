<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Vendors;

use App\Modules\Integrations\Models\Vendor;
use App\Support\Exceptions\ApiException;

/**
 * Shared vendor write rules: one e-mail per organization (409 `vendor_email_taken` with
 * `details.existing_id`), and the attribute keys a write may set.
 */
final class VendorAttributes
{
    /**
     * @var list<string>
     */
    public const array FIELDS = [
        'name', 'name_en', 'cr_number', 'vat_number', 'email', 'contact_name', 'phone', 'region_id', 'city', 'status', 'notes',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function only(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    public static function assertEmailFree(int $organizationId, string $email, ?int $exceptVendorId = null): void
    {
        $existing = Vendor::query()
            ->where('organization_id', $organizationId)
            ->where('email', mb_strtolower(trim($email)))
            ->when($exceptVendorId !== null, static fn ($query) => $query->whereKeyNot($exceptVendorId))
            ->value('public_id');

        if (is_string($existing)) {
            throw self::emailTaken($existing);
        }
    }

    public static function emailTaken(?string $existingId): ApiException
    {
        return new ApiException(
            errorCode: 'vendor_email_taken',
            messageKey: 'integrations.errors.vendor_email_taken',
            status: 409,
            details: $existingId === null ? [] : ['existing_id' => $existingId],
        );
    }
}
