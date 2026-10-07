<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * `competition_presets.tier` (RELEASE_SCOPE.md §2.2): the three preset cards of the rules step,
 * from the fewest to the most protective rules. Null on the untiered reference presets, which the
 * lookups list only while the release scope enables `advanced_rules`.
 */
enum PresetTier: string
{
    case Simple = 'simple';
    case Standard = 'standard';
    case Protected = 'protected';

    /** Label in the given locale (default: the app locale). Key: `catalog.enums.preset_tier.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('catalog.enums.preset_tier.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
