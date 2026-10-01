<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Vendors;

use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Modules\Integrations\Services\VendorDirectory;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Changes a vendor (app and public `PATCH /vendors/{vendor}`, the public upsert, the import).
 * Only the keys present in `$data` change; `category_ids` and `external_refs`, when present,
 * replace the whole list. The organization link is re-matched when the e-mail or CR changes.
 */
final readonly class UpdateVendor
{
    public function __construct(
        private ExternalRefs $externalRefs,
        private VendorDirectory $directory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Vendor $vendor, array $data, Actor $actor): Vendor
    {
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
            VendorAttributes::assertEmailFree($vendor->organization_id, $data['email'], $vendor->id);
        }

        try {
            return DB::transaction(function () use ($vendor, $data, $actor): Vendor {
                $vendor = Vendor::query()->lockForUpdate()->findOrFail($vendor->id);
                $vendor->fill(VendorAttributes::only($data));

                if ($vendor->isDirty(['email', 'cr_number'])) {
                    $vendor->linked_organization_id = $this->directory
                        ->matchOrganization($vendor->organization_id, $vendor->email, $vendor->cr_number)?->id;
                }

                $vendor->save();
                $changes = AuditLogger::diff($vendor);

                if (array_key_exists('category_ids', $data) && is_array($data['category_ids'])) {
                    $sync = $vendor->categories()->sync($data['category_ids']);

                    if ($sync['attached'] !== [] || $sync['detached'] !== []) {
                        $changes['categories'] = ['from' => null, 'to' => $data['category_ids']];
                    }
                }

                if (array_key_exists('external_refs', $data) && is_array($data['external_refs'])) {
                    /** @var list<array{system: string, type: string, id: string, number?: string|null, url?: string|null}> $refs */
                    $refs = $data['external_refs'];
                    $this->externalRefs->sync($vendor, $vendor->organization_id, $refs, $actor->apiClientId);
                }

                if ($changes !== []) {
                    AuditLogger::log('vendor.updated', $vendor, $changes, actor: $actor);
                }

                return $vendor;
            });
        } catch (UniqueConstraintViolationException) {
            throw VendorAttributes::emailTaken(null);
        }
    }
}
