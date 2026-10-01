<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\OrganizationProfileData;
use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /organization` (API.md §1.3): any register field of the organization except
 * `cr_number`, which is immutable. Dispatches `OrganizationUpdated` when something changed.
 */
final class UpdateOrganization
{
    public function handle(Organization $organization, OrganizationProfileData $data, Actor $actor): Organization
    {
        return DB::transaction(static function () use ($organization, $data, $actor): Organization {
            /** @var Organization $organization */
            $organization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $organization->fill($data->attributes);

            // CONTRACT-GAP: a VAT number only means something while the organization is VAT
            // registered; switching `vat_registered` off clears it.
            if (! $organization->vat_registered) {
                $organization->vat_number = null;
            }

            $organization->save();

            $changes = AuditLogger::diff($organization);
            $changedFields = array_keys($changes);

            if ($data->categoryIds !== null) {
                $before = $organization->categories()->pluck('categories.id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
                $after = collect($data->categoryIds)->unique()->sort()->values()->all();

                if ($before !== $after) {
                    $organization->categories()->sync($after);
                    $changes['category_ids'] = ['from' => $before, 'to' => $after];
                    $changedFields[] = 'category_ids';
                }
            }

            if ($changedFields !== []) {
                AuditLogger::log('organization.updated', $organization, $changes, actor: $actor);

                event(new OrganizationUpdated($organization, $changedFields, $actor));
            }

            return $organization;
        });
    }
}
