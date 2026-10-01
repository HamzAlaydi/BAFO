<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The sponsorship endpoints of a competition (API.md §1.7 "Sponsorship", ARCHITECTURE §8.3).
 * Registered as gate abilities (the Competition policy belongs to Competitions):
 *
 * - `billing.sponsorship.view` (show, quote): any member of the issuer organization;
 * - `billing.sponsorship.manage` (configure): `competitions.manage` on the competition;
 * - `billing.sponsorship.checkout`: `competitions.manage` and `billing.purchase`.
 *
 * Anyone outside the issuer organization gets 404: sponsorship is issuer-only and its
 * existence is never revealed to participants (§13.5 "What each audience sees").
 */
final class SponsorshipPolicy
{
    public const string VIEW = 'billing.sponsorship.view';

    public const string MANAGE = 'billing.sponsorship.manage';

    public const string CHECKOUT = 'billing.sponsorship.checkout';

    public function view(User $user, Competition $competition): Response
    {
        return $this->isIssuer($user, $competition) ? Response::allow() : Response::denyAsNotFound();
    }

    public function manage(User $user, Competition $competition): Response
    {
        if (! $this->isIssuer($user, $competition)) {
            return Response::denyAsNotFound();
        }

        return $this->canManage($user, $competition) ? Response::allow() : Response::deny();
    }

    public function checkout(User $user, Competition $competition): Response
    {
        if (! $this->isIssuer($user, $competition)) {
            return Response::denyAsNotFound();
        }

        return $this->canManage($user, $competition) && $user->hasPermission(Permission::BillingPurchase)
            ? Response::allow()
            : Response::deny();
    }

    private function isIssuer(User $user, Competition $competition): bool
    {
        $organizationId = $user->membership?->organization_id;

        return $organizationId !== null && $organizationId === $competition->organization_id && $user->permissions() !== [];
    }

    /**
     * `competitions.manage` on a competition (ARCHITECTURE §8.1): `competitions.manage_all`, or
     * `competitions.create` and being its creator.
     */
    private function canManage(User $user, Competition $competition): bool
    {
        return $user->hasPermission(Permission::CompetitionsManageAll)
            || ($user->hasPermission(Permission::CompetitionsCreate) && $competition->created_by_user_id === $user->id);
    }
}
