<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Invoices (API.md §1.7): perm `billing.view` within the buyer organization; another
 * organization's invoice is 404.
 */
final class InvoicePolicy
{
    public function viewAny(User $user): Response
    {
        return $user->hasPermission(Permission::BillingView) ? Response::allow() : Response::deny();
    }

    public function view(User $user, Invoice $invoice): Response
    {
        if ($user->membership?->organization_id !== $invoice->organization_id) {
            return Response::denyAsNotFound();
        }

        return $this->viewAny($user);
    }
}
