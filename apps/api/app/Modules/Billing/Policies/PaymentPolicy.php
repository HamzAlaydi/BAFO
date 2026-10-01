<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Payment;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Payments (API.md §1.7, ARCHITECTURE §13.1):
 *
 * - `purchase`: perm `billing.purchase` (checkout, trial, coupon validation);
 * - `view`: perm `billing.view`, or the payer. Another organization's payment is 404.
 */
final class PaymentPolicy
{
    public function purchase(User $user): Response
    {
        return $user->hasPermission(Permission::BillingPurchase) ? Response::allow() : Response::deny();
    }

    public function view(User $user, Payment $payment): Response
    {
        if ($user->membership?->organization_id !== $payment->organization_id) {
            return Response::denyAsNotFound();
        }

        return $user->id === $payment->created_by_user_id || $user->hasPermission(Permission::BillingView)
            ? Response::allow()
            : Response::deny();
    }
}
