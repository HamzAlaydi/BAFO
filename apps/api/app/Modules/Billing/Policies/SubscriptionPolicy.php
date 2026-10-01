<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Subscriptions (API.md §1.7): the overview needs `billing.view`; starting the trial needs
 * `billing.purchase`.
 */
final class SubscriptionPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->hasPermission(Permission::BillingView) ? Response::allow() : Response::deny();
    }

    public function create(User $user): Response
    {
        return $user->hasPermission(Permission::BillingPurchase) ? Response::allow() : Response::deny();
    }
}
