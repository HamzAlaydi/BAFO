<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Enums\ErpSyncStatus;
use App\Modules\Bidding\Events\AwardErpSynced;
use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The ERP write-back (API.md §3.5 `POST /awards/{award}/erp-sync`, flow F6): the issuer's ERP
 * reports `synced` or `failed`, with the references it created (for example the purchase order),
 * which are stored on the award.
 */
final readonly class RecordAwardErpSync
{
    /**
     * @param  list<array{system: string, type: string, id: string, number?: string|null, url?: string|null}>  $refs
     */
    public function handle(Award $award, ErpSyncStatus $status, ?string $message, array $refs, Actor $actor): Award
    {
        return DB::transaction(function () use ($award, $status, $message, $refs, $actor): Award {
            $locked = Award::query()->whereKey($award->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isIssued()) {
                throw new ApiException('award_not_active', 'bidding.errors.award_not_active', 409);
            }

            $issuerId = (int) Competition::query()->whereKey($locked->competition_id)->value('organization_id');

            foreach ($refs as $ref) {
                $this->storeRef($locked, $issuerId, $ref, $actor);
            }

            $locked->forceFill([
                'erp_sync_status' => $status,
                'erp_sync_message' => $message,
                'erp_synced_at' => $status === ErpSyncStatus::Synced ? Date::now() : $locked->erp_synced_at,
            ])->save();

            AuditLogger::log('award.erp_synced', $locked, meta: [
                'status' => $status->value,
                'refs' => count($refs),
            ], actor: $actor, organizationId: $issuerId);

            event(new AwardErpSynced($locked));

            return $locked;
        });
    }

    /**
     * @param  array{system: string, type: string, id: string, number?: string|null, url?: string|null}  $ref
     */
    private function storeRef(Award $award, int $issuerId, array $ref, Actor $actor): void
    {
        $existing = ExternalRef::query()
            ->where('organization_id', $issuerId)
            ->where('refable_type', $award->getMorphClass())
            ->where('system', $ref['system'])
            ->where('type', $ref['type'])
            ->where('value', $ref['id'])
            ->first();

        // CONTRACT-GAP: API.md §3.5 says the refs "are stored on the award". They are upserted by
        // (system, type, id); a key already stored on another award of the issuer answers
        // 409 `external_ref_conflict` (the Integrations code for a reused ERP key).
        if ($existing !== null && $existing->refable_id !== $award->id) {
            $other = Award::query()->whereKey($existing->refable_id)->value('public_id');

            throw new ApiException('external_ref_conflict', 'bidding.errors.external_ref_conflict', 409, details: [
                'existing_id' => is_string($other) ? $other : null,
            ]);
        }

        $attributes = [
            'number' => $ref['number'] ?? null,
            'url' => $ref['url'] ?? null,
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return;
        }

        ExternalRef::query()->create([
            'organization_id' => $issuerId,
            'refable_type' => $award->getMorphClass(),
            'refable_id' => $award->id,
            'system' => $ref['system'],
            'type' => $ref['type'],
            'value' => $ref['id'],
            ...$attributes,
            'created_by_api_client_id' => $actor->apiClientId,
        ]);
    }
}
