<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Data;

/**
 * A validated create or update request for a competition (API.md §1.4, §3.4), already mapped
 * to columns: lookups are internal ids, `rules` is flattened to the typed rules columns.
 *
 *   $columns       competition columns present in the request
 *   $fields        top-level request keys that were sent (for `competition_not_editable`)
 *   $presetCode    `preset_code` when sent (null clears it on update)
 *   $externalRefs  public API `external_refs` (replaces the list when not null)
 */
final readonly class CompetitionInput
{
    /**
     * @param  array<string, mixed>  $columns
     * @param  list<string>  $fields
     * @param  list<array{system: string, type: string, id: string, number: string|null, url: string|null}>|null  $externalRefs
     */
    public function __construct(
        public array $columns,
        public array $fields,
        public ?string $presetCode = null,
        public ?array $externalRefs = null,
    ) {}

    public function has(string $field): bool
    {
        return in_array($field, $this->fields, true);
    }
}
