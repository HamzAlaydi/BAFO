<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Admin panel (ARCHITECTURE §16): the per-organization switches `api_enabled` (R1),
 * `auction_enabled` (R5 guardrail) and `sponsorship_enabled` (R4).
 */
final class UpdateOrganizationFeatures
{
    /**
     * @param  array{api_enabled?: bool, auction_enabled?: bool, sponsorship_enabled?: bool}  $features
     */
    public function handle(Organization $organization, array $features, Actor $actor): Organization
    {
        return DB::transaction(static function () use ($organization, $features, $actor): Organization {
            /** @var Organization $organization */
            $organization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $organization->forceFill(array_intersect_key($features, array_flip(['api_enabled', 'auction_enabled', 'sponsorship_enabled'])))->save();
            $changes = AuditLogger::diff($organization);

            if ($changes !== []) {
                AuditLogger::log('organization.features_updated', $organization, $changes, actor: $actor, organizationId: $organization->id);

                event(new OrganizationUpdated($organization, array_keys($changes), $actor));
            }

            return $organization;
        });
    }
}
