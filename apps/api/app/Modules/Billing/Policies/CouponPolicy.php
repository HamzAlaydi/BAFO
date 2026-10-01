<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Coupons and vouchers (API.md §1.7): the organization's vouchers need `billing.view`;
 * validating a code needs `billing.purchase`.
 */
final class CouponPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->hasPermission(Permission::BillingView) ? Response::allow() : Response::deny();
    }

    public function validate(User $user): Response
    {
        return $user->hasPermission(Permission::BillingPurchase) ? Response::allow() : Response::deny();
    }
}
