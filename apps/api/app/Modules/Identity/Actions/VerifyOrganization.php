<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Admin panel (ARCHITECTURE §16): grants or removes the "verified" badge (`verified_at`).
 */
final class VerifyOrganization
{
    public function handle(Organization $organization, bool $verified, Actor $actor): Organization
    {
        return DB::transaction(static function () use ($organization, $verified, $actor): Organization {
            /** @var Organization $organization */
            $organization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            if ($organization->isVerified() === $verified) {
                return $organization;
            }

            $organization->forceFill(['verified_at' => $verified ? Date::now() : null])->save();

            AuditLogger::log($verified ? 'organization.verified' : 'organization.unverified', $organization, actor: $actor, organizationId: $organization->id);

            event(new OrganizationUpdated($organization, ['verified_at'], $actor));

            return $organization;
        });
    }
}
