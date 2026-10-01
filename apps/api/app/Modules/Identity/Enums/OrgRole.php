<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

use App\Modules\Identity\Models\Membership;

/**
 * `memberships.role` (ARCHITECTURE §5.3, §8.1).
 */
enum OrgRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * The permission matrix of ARCHITECTURE §8.1 (the only source). The owner is always treated
     * as `can_award` and `can_purchase`.
     *
     * @return list<Permission>
     */
    public static function permissions(Membership $membership): array
    {
        $role = $membership->role;

        if ($role === self::Owner) {
            return Permission::cases();
        }

        $permissions = [Permission::CompetitionsCreate, Permission::ParticipationSubmitOffers];

        if ($role === self::Admin) {
            array_push(
                $permissions,
                Permission::OrganizationUpdate,
                Permission::TeamManage,
                Permission::BillingView,
                Permission::CompetitionsManageAll,
                Permission::IntegrationsManage,
            );
        }

        if ($membership->can_purchase) {
            $permissions[] = Permission::BillingPurchase;
        }

        if ($membership->can_award) {
            $permissions[] = Permission::CompetitionsAward;
        }

        // Keep the catalogue order of Permission::cases() so clients get a stable list.
        return array_values(array_filter(
            Permission::cases(),
            static fn (Permission $permission): bool => in_array($permission, $permissions, true),
        ));
    }

    /** Label in the given locale (default: the app locale). Key: `identity.enums.org_role.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('identity.enums.org_role.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
