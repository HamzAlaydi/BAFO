<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;

/**
 * ERP keys of a competition (`external_refs` rows with refable `competition`), written by the
 * public API create and update (API.md §3.4: `external_refs` replaces the list).
 *
 * CONTRACT-GAP: `external_refs` is an Integrations table and §3.6 lists no contract to write it;
 * the polymorphic rows of a competition are written here, inside the competition transaction.
 * A key already used by another object of the organization is 409 `external_ref_conflict`
 * (`details.existing_id`), as for vendors.
 */
final class CompetitionExternalRefs
{
    /**
     * @param  list<array{system: string, type: string, id: string, number: string|null, url: string|null}>  $refs
     */
    public function replace(Competition $competition, array $refs, Actor $actor): void
    {
        ExternalRef::query()
            ->where('refable_type', $competition->getMorphClass())
            ->where('refable_id', $competition->id)
            ->delete();

        foreach ($refs as $ref) {
            $existing = ExternalRef::query()
                ->where('organization_id', $competition->organization_id)
                ->where('refable_type', $competition->getMorphClass())
                ->where('system', $ref['system'])
                ->where('type', $ref['type'])
                ->where('value', $ref['id'])
                ->first();

            if ($existing !== null) {
                $other = Competition::withTrashed()->find($existing->refable_id);

                throw new ApiException(
                    errorCode: 'external_ref_conflict',
                    messageKey: 'competitions.errors.external_ref_conflict',
                    status: 409,
                    details: ['existing_id' => $other?->public_id],
                );
            }

            ExternalRef::query()->create([
                'organization_id' => $competition->organization_id,
                'refable_type' => $competition->getMorphClass(),
                'refable_id' => $competition->id,
                'system' => $ref['system'],
                'type' => $ref['type'],
                'value' => $ref['id'],
                'number' => $ref['number'],
                'url' => $ref['url'],
                'created_by_api_client_id' => $actor->apiClientId,
            ]);
        }
    }
}
