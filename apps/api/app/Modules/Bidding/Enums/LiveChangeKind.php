<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Enums;

/**
 * `last_change.kind` of the live snapshots (API.md §2.8, ARCHITECTURE §7.10): what made the
 * live-state version move. `snapshot` marks a REST read.
 */
enum LiveChangeKind: string
{
    case Offer = 'offer';
    case Extension = 'extension';
    case Status = 'status';
    case Bafo = 'bafo';
    case Award = 'award';
    case Void = 'void';
    case Snapshot = 'snapshot';

    /** Label in the given locale (default: the app locale). Key: `bidding.enums.live_change_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('bidding.enums.live_change_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
