<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Organization permissions (ARCHITECTURE §8.1). `OrgRole::permissions()` is the only source.
 */
enum Permission: string
{
    case OrganizationUpdate = 'organization.update';
    case TeamManage = 'team.manage';
    case BillingView = 'billing.view';
    case BillingPurchase = 'billing.purchase';
    case CompetitionsCreate = 'competitions.create';
    case CompetitionsManageAll = 'competitions.manage_all';
    case CompetitionsAward = 'competitions.award';
    case ParticipationSubmitOffers = 'participation.submit_offers';
    case IntegrationsManage = 'integrations.manage';
    case AccountDeleteOrganization = 'account.delete_organization';

    /** Label in the given locale (default: the app locale). Key: `identity.enums.permission.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.permission.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
