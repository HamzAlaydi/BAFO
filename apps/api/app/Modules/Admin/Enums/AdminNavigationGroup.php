<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The panel's navigation groups, in sidebar order (ARCHITECTURE §16). Labels:
 * `admin.enums.admin_navigation_group.<value>`, resolved in the panel language at render time.
 */
enum AdminNavigationGroup: string implements HasLabel
{
    case Customers = 'customers';
    case Competitions = 'competitions';
    case Billing = 'billing';
    case Integrations = 'integrations';
    case Lookups = 'lookups';
    case Content = 'content';
    case System = 'system';

    /** Label in the given locale (default: the app locale). Key: `admin.enums.admin_navigation_group.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('admin.enums.admin_navigation_group.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
