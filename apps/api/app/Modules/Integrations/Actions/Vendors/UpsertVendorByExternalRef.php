<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Vendors;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Public `PUT /vendors/external/{system}/{external_id}` (API.md §3.3): an idempotent upsert by the
 * ERP key (ref type `supplier`).
 *
 *   - The key is known → the vendor's fields are replaced (200).
 *   - Unknown key, but a vendor of the organization has the e-mail → that vendor takes the key
 *     and its fields are replaced (200).
 *   - Otherwise → a new vendor with the key (201).
 *
 * CONTRACT-GAP: "replaces its fields" is read as a full replacement of the create fields, except
 * `status` and `notes`, which keep their value when the body omits them (an ERP re-sync must not
 * unblock a vendor or wipe the issuer's private notes).
 */
final readonly class UpsertVendorByExternalRef
{
    /**
     * @var list<string>
     */
    private const array REPLACED_FIELDS = ['name', 'name_en', 'cr_number', 'vat_number', 'email', 'contact_name', 'phone', 'region_id', 'city'];

    public function __construct(
        private CreateVendor $create,
        private UpdateVendor $update,
        private ExternalRefs $externalRefs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Vendor, 1: bool} the vendor and whether it was created
     */
    public function handle(Organization $organization, string $system, string $externalId, array $data, Actor $actor): array
    {
        return DB::transaction(function () use ($organization, $system, $externalId, $data, $actor): array {
            $vendorId = $this->externalRefs->findRefableId($organization->id, 'vendor', $system, ExternalRefs::TYPE_SUPPLIER, $externalId);
            $vendor = $vendorId !== null
                ? Vendor::query()->find($vendorId)
                : Vendor::query()->where('organization_id', $organization->id)
                    ->where('email', mb_strtolower(trim((string) $data['email'])))->first();

            if ($vendor === null) {
                $vendor = $this->create->handle($organization, $data, VendorSource::Api, $actor);
                $this->externalRefs->put($vendor, $organization->id, $system, ExternalRefs::TYPE_SUPPLIER, $externalId, apiClientId: $actor->apiClientId);

                return [$vendor, true];
            }

            $replacement = array_fill_keys(self::REPLACED_FIELDS, null);
            $replacement['category_ids'] = [];

            $vendor = $this->update->handle($vendor, [...$replacement, ...$data], $actor);
            $this->externalRefs->put($vendor, $organization->id, $system, ExternalRefs::TYPE_SUPPLIER, $externalId, apiClientId: $actor->apiClientId);

            return [$vendor, false];
        });
    }
}
