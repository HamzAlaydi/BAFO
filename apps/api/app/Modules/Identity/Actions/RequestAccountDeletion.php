<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Mail\AccountDeletionScheduledMail;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuthTokens;
use App\Modules\Identity\Services\DeletionBlockers;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * `POST /account/deletion` (ARCHITECTURE §13.8, store requirement): the owner deletes the whole
 * organization, anyone else leaves (scope `user`). The request runs 14 days later; until then
 * it can be cancelled. All other tokens are revoked and a confirmation mail is sent.
 */
final readonly class RequestAccountDeletion
{
    public function __construct(
        private DeletionBlockers $blockers,
        private AuthTokens $tokens,
    ) {}

    public function handle(User $user, string $password, ?string $reason, ?string $bearerToken, Actor $actor): AccountDeletionRequest
    {
        if ($user->password === null || ! Hash::check($password, $user->password)) {
            throw IdentityError::make('password_incorrect');
        }

        return DB::transaction(function () use ($user, $reason, $bearerToken, $actor): AccountDeletionRequest {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $pending = AccountDeletionRequest::query()
                ->where('user_id', $user->id)
                ->where('status', DeletionStatus::Pending->value)
                ->exists();

            if ($pending) {
                throw IdentityError::make('account_deletion_pending');
            }

            $membership = $user->membership()->with('organization')->firstOrFail();
            $organization = $membership->organization;
            $scope = $membership->isOwner() ? DeletionScope::Organization : DeletionScope::User;

            if ($scope === DeletionScope::Organization) {
                $blockers = $this->blockers->for($organization);

                if ($blockers !== []) {
                    throw IdentityError::make('account_deletion_blocked', ['blockers' => $blockers]);
                }
            }

            $days = config('bafo.identity.account_deletion_grace_days', 14);

            $request = AccountDeletionRequest::query()->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'scope' => $scope,
                'reason' => $reason,
                'status' => DeletionStatus::Pending,
                'scheduled_for' => Date::now()->addDays(is_numeric($days) ? (int) $days : 14),
            ]);

            $this->tokens->revokeAll($user, $this->tokens->findForUser($user, $bearerToken)?->id);

            // CONTRACT-GAP: `account_deletion_requests` has no morph alias (§4.9), so the audit
            // subject is the requesting user and the request is named in `meta`.
            AuditLogger::log('account_deletion.requested', $user, meta: [
                'account_deletion_request_id' => $request->public_id,
                'scope' => $scope->value,
            ], actor: $actor, organizationId: $organization->id);

            Mail::to($user->email)->queue(
                (new AccountDeletionScheduledMail($user->name, $scope, $request->scheduled_for))->locale($user->locale),
            );

            return $request;
        });
    }
}
