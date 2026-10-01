<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

/**
 * `admins.role` (ARCHITECTURE §5.9, §8.7). Operators cannot manage admins, plans, prices, coupons or settings.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operator';

    /** Label in the given locale (default: the app locale). Key: `admin.enums.admin_role.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('admin.enums.admin_role.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
