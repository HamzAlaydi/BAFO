<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * Public API scopes (ARCHITECTURE §14.4), stored in `api_clients.scopes`.
 */
enum ApiScope: string
{
    case OrganizationRead = 'organization:read';
    case LookupsRead = 'lookups:read';
    case VendorsRead = 'vendors:read';
    case VendorsWrite = 'vendors:write';
    case CompetitionsRead = 'competitions:read';
    case CompetitionsWrite = 'competitions:write';
    case CompetitionsPublish = 'competitions:publish';
    case CompetitionsManage = 'competitions:manage';
    case InvitationsRead = 'invitations:read';
    case InvitationsWrite = 'invitations:write';
    case OffersRead = 'offers:read';
    case AwardsRead = 'awards:read';
    case AwardsSync = 'awards:sync';
    case WebhooksManage = 'webhooks:manage';

    /**
     * English scope descriptions for `Passport::tokensCan()` (ARCHITECTURE §14.2).
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (self::cases() as $scope) {
            $descriptions[$scope->value] = $scope->label('en');
        }

        return $descriptions;
    }

    /**
     * Scopes pre-selected when a client is created in the dashboard (ARCHITECTURE §14.4): every read
     * scope plus `vendors:write`, `competitions:write`, `invitations:write` and `awards:sync`.
     * `competitions:publish`, `competitions:manage` and `webhooks:manage` are opt-in.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $scope): bool => ! in_array(
                $scope,
                [self::CompetitionsPublish, self::CompetitionsManage, self::WebhooksManage],
                true,
            ),
        ));
    }

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.api_scope.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.api_scope.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
