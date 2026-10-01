<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Enums;

/**
 * `competitions.direction` and `competition_presets.direction` (ARCHITECTURE §5.5, §7.1).
 *
 * CONTRACT-GAP: the contract does not say which module owns the enum; it lives with the competition
 * (Competitions) and Catalog presets read it.
 */
enum Direction: string
{
    case Tender = 'tender';
    case Auction = 'auction';

    /**
     * The comparison sign `d` of ARCHITECTURE §7.1: tender → −1, auction → +1.
     * "a is better than b" ⇔ d × (a − b) > 0; `rank_key = −d × amount`.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Tender => -1,
            self::Auction => 1,
        };
    }

    /** Label in the given locale (default: the app locale). Key: `competitions.enums.direction.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('competitions.enums.direction.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
