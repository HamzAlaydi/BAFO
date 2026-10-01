<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Vendors;

use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * App `DELETE /vendors/{vendor}`: vendors are archived, never deleted (invitations reference them).
 */
final class ArchiveVendor
{
    public function handle(Vendor $vendor, Actor $actor): Vendor
    {
        return DB::transaction(static function () use ($vendor, $actor): Vendor {
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($vendor->id);

            if ($vendor->status === VendorStatus::Archived) {
                return $vendor;
            }

            $vendor->fill(['status' => VendorStatus::Archived])->save();

            AuditLogger::log('vendor.archived', $vendor, AuditLogger::diff($vendor, ['status']), actor: $actor);

            return $vendor;
        });
    }
}
