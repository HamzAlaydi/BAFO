<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;

/**
 * The account rules shared by sign-in (login, OTP verification, team invitation accept) and the
 * `EnsureAccountActive` middleware (ARCHITECTURE §8.1, §13.9).
 */
final class AccountGate
{
    /**
     * Sign-in: the membership must be active and the organization not suspended.
     *
     * @throws ApiException `account_inactive` or `organization_suspended` (403)
     */
    public function assertCanSignIn(User $user): void
    {
        $membership = $user->membership;

        if ($membership === null || $membership->status !== MembershipStatus::Active) {
            throw IdentityError::make('account_inactive');
        }

        $status = $membership->organization?->status;

        if ($status === OrganizationStatus::Suspended) {
            throw IdentityError::make('organization_suspended');
        }

        if ($status !== OrganizationStatus::Active) {
            throw IdentityError::make('account_inactive');
        }
    }

    /**
     * The §8.1 account gate for an authenticated request, in the contract order: user status,
     * verified e-mail, active membership, active organization. Null when the request may go on.
     */
    public function blockerFor(User $user): ?ApiException
    {
        // CONTRACT-GAP: a `pending_verification` user fails both the status and the e-mail rule of
        // §8.1; the more useful `email_not_verified` wins for that status.
        if ($user->status === UserStatus::PendingVerification || ! $user->hasVerifiedEmail()) {
            return IdentityError::make('email_not_verified');
        }

        if ($user->status !== UserStatus::Active) {
            return IdentityError::make('account_inactive');
        }

        try {
            $this->assertCanSignIn($user);
        } catch (ApiException $e) {
            return $e;
        }

        return null;
    }
}
