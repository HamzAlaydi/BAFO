<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competition_extensions.kind` (ARCHITECTURE §5.5, §7.7, §7.17).
 */
enum ExtensionKind: string
{
    case Auto = 'auto';
    case Manual = 'manual';
    case Admin = 'admin';

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.extension_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.extension_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
