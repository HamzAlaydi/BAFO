<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * `sponsored_passes.status` (ARCHITECTURE §5.7, §6.3).
 */
enum PassStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case Joined = 'joined';
    case Released = 'released';
    case Unused = 'unused';
    case Void = 'void';

    /**
     * The statuses that hold (or will hold) a slot for the invitation: at most one per invitation
     * (partial unique `sponsored_passes_one_live_per_invitation`).
     *
     * @return list<string>
     */
    public static function live(): array
    {
        return [self::Pending->value, self::Reserved->value, self::Joined->value];
    }

    /**
     * The statuses that occupy a funded slot (ARCHITECTURE §5.7 "free slots").
     *
     * @return list<string>
     */
    public static function occupying(): array
    {
        return [self::Reserved->value, self::Joined->value];
    }

    /** Label in the given locale (default: the app locale). Key: `billing.enums.pass_status.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('billing.enums.pass_status.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
