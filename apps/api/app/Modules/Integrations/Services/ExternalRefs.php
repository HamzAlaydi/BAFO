<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * ERP keys attached to BAFO objects (ARCHITECTURE §5.4 `external_refs`, API.md §2.12
 * `ExternalRef`): `{"system", "type", "id", "number", "url"}` where `id` is the ERP key.
 *
 * A key is unique per organization, refable type, system and type. Attaching a key that
 * another object of the organization already holds raises 409 `external_ref_conflict` with
 * `details.existing_id` (the public id of that object).
 */
final class ExternalRefs
{
    public const string TYPE_SUPPLIER = 'supplier';

    /**
     * Replaces the object's refs with `$refs` (API.md: "`external_refs` replaces the whole list").
     *
     * @param  list<array{system: string, type: string, id: string, number?: string|null, url?: string|null}>  $refs
     */
    public function sync(Model $refable, int $organizationId, array $refs, ?int $apiClientId = null): void
    {
        DB::transaction(function () use ($refable, $organizationId, $refs, $apiClientId): void {
            $keep = [];

            foreach ($refs as $ref) {
                $keep[] = $this->put($refable, $organizationId, $ref['system'], $ref['type'], $ref['id'],
                    $ref['number'] ?? null, $ref['url'] ?? null, $apiClientId)->id;
            }

            ExternalRef::query()
                ->where('refable_type', $refable->getMorphClass())
                ->where('refable_id', $refable->getKey())
                ->whereNotIn('id', $keep)
                ->delete();
        });
    }

    /**
     * Adds or updates one key on the object.
     */
    public function put(
        Model $refable,
        int $organizationId,
        string $system,
        string $type,
        string $value,
        ?string $number = null,
        ?string $url = null,
        ?int $apiClientId = null,
    ): ExternalRef {
        $existing = ExternalRef::query()
            ->where('organization_id', $organizationId)
            ->where('refable_type', $refable->getMorphClass())
            ->where('system', $system)
            ->where('type', $type)
            ->where('value', $value)
            ->lockForUpdate()
            ->first();

        if ($existing !== null && $existing->refable_id !== (int) $refable->getKey()) {
            throw new ApiException(
                errorCode: 'external_ref_conflict',
                messageKey: 'integrations.errors.external_ref_conflict',
                status: 409,
                details: ['existing_id' => $this->publicIdOf($existing)],
            );
        }

        $ref = $existing ?? new ExternalRef([
            'organization_id' => $organizationId,
            'refable_type' => $refable->getMorphClass(),
            'refable_id' => $refable->getKey(),
            'system' => $system,
            'type' => $type,
            'value' => $value,
            'created_by_api_client_id' => $apiClientId,
        ]);

        $ref->fill(['number' => $number, 'url' => $url])->save();

        return $ref;
    }

    /**
     * The id of the object of `$refableType` holding the key, or null.
     */
    public function findRefableId(int $organizationId, string $refableType, string $system, string $type, string $value): ?int
    {
        $id = ExternalRef::query()
            ->where('organization_id', $organizationId)
            ->where('refable_type', $refableType)
            ->where('system', $system)
            ->where('type', $type)
            ->where('value', $value)
            ->value('refable_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  iterable<ExternalRef>  $refs
     * @return list<array{system: string, type: string, id: string, number: string|null, url: string|null}>
     */
    public static function present(iterable $refs): array
    {
        $out = [];

        foreach ($refs as $ref) {
            $out[] = self::presentOne($ref);
        }

        usort($out, static fn (array $a, array $b): int => [$a['system'], $a['type'], $a['id']] <=> [$b['system'], $b['type'], $b['id']]);

        return $out;
    }

    /**
     * @return array{system: string, type: string, id: string, number: string|null, url: string|null}
     */
    public static function presentOne(ExternalRef $ref): array
    {
        return [
            'system' => $ref->system,
            'type' => $ref->type,
            'id' => $ref->value,
            'number' => $ref->number,
            'url' => $ref->url,
        ];
    }

    private function publicIdOf(ExternalRef $ref): ?string
    {
        $class = Relation::getMorphedModel($ref->refable_type);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        $publicId = $class::query()->whereKey($ref->refable_id)->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }
}
