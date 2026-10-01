<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The admin transitions of `organizations.status` (ARCHITECTURE §6.6): `active ↔ suspended`.
 * `active → deleted` belongs to the account deletion executor only.
 */
final class OrganizationStatusMachine
{
    /**
     * @throws ApiException `invalid_state_transition` (409, `details.from`, `details.to`)
     */
    public function transition(Organization $organization, OrganizationStatus $to, Actor $actor, ?string $reason = null): Organization
    {
        return DB::transaction(static function () use ($organization, $to, $actor, $reason): Organization {
            /** @var Organization $organization */
            $organization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $from = $organization->status;
            $allowed = ($from === OrganizationStatus::Active && $to === OrganizationStatus::Suspended)
                || ($from === OrganizationStatus::Suspended && $to === OrganizationStatus::Active);

            if (! $allowed) {
                throw new ApiException(
                    errorCode: 'invalid_state_transition',
                    status: 409,
                    details: ['from' => $from->value, 'to' => $to->value],
                );
            }

            $suspending = $to === OrganizationStatus::Suspended;

            $organization->forceFill([
                'status' => $to,
                'suspended_at' => $suspending ? Date::now() : null,
                'suspension_reason' => $suspending ? $reason : null,
            ])->save();

            AuditLogger::log(
                $suspending ? 'organization.suspended' : 'organization.unsuspended',
                $organization,
                AuditLogger::diff($organization, ['status']),
                $suspending ? ['reason' => $reason] : [],
                $actor,
                $organization->id,
            );

            event(new OrganizationUpdated($organization, ['status'], $actor));

            return $organization;
        });
    }
}
