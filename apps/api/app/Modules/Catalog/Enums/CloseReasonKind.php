<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * `close_reasons.kind` (ARCHITECTURE §5.2).
 */
enum CloseReasonKind: string
{
    case Cancel = 'cancel';
    case NotAwarded = 'not_awarded';
    case AwardJustification = 'award_justification';
    case VoidOffer = 'void_offer';

    /** Label in the given locale (default: the app locale). Key: `catalog.enums.close_reason_kind.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('catalog.enums.close_reason_kind.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
