<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Vendors;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Modules\Integrations\Services\VendorDirectory;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Adds a vendor to the issuer's directory (app `POST /vendors`, public `POST /vendors` and the
 * upsert, the import). `linked_organization_id` is set when the e-mail or CR matches a BAFO
 * organization (ARCHITECTURE §5.4).
 */
final readonly class CreateVendor
{
    public function __construct(
        private ExternalRefs $externalRefs,
        private VendorDirectory $directory,
    ) {}

    /**
     * @param  array<string, mixed>  $data  VendorAttributes::FIELDS, plus `category_ids` (internal ids)
     *                                      and `external_refs` (API.md §2.12 ExternalRef input)
     */
    public function handle(Organization $organization, array $data, VendorSource $source, Actor $actor): Vendor
    {
        $email = mb_strtolower(trim((string) $data['email']));
        VendorAttributes::assertEmailFree($organization->id, $email);

        try {
            return DB::transaction(function () use ($organization, $data, $source, $actor, $email): Vendor {
                $crNumber = isset($data['cr_number']) && is_string($data['cr_number']) ? $data['cr_number'] : null;
                $linked = $this->directory->matchOrganization($organization->id, $email, $crNumber);

                $vendor = new Vendor([
                    'status' => VendorStatus::Active,
                    ...VendorAttributes::only($data),
                    'organization_id' => $organization->id,
                    'email' => $email,
                    'source' => $source,
                    'linked_organization_id' => $linked?->id,
                ]);
                $vendor->save();

                if (isset($data['category_ids']) && is_array($data['category_ids'])) {
                    $vendor->categories()->sync($data['category_ids']);
                }

                if (isset($data['external_refs']) && is_array($data['external_refs'])) {
                    /** @var list<array{system: string, type: string, id: string, number?: string|null, url?: string|null}> $refs */
                    $refs = $data['external_refs'];
                    $this->externalRefs->sync($vendor, $organization->id, $refs, $actor->apiClientId);
                }

                AuditLogger::log('vendor.created', $vendor, meta: ['source' => $source->value], actor: $actor);

                return $vendor;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent write took the e-mail between the check and the insert.
            throw VendorAttributes::emailTaken(
                Vendor::query()->where('organization_id', $organization->id)->where('email', $email)->value('public_id'),
            );
        }
    }
}
