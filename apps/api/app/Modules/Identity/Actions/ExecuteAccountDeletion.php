<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\DeletionBlockers;
use App\Modules\Identity\Services\UserAnonymiser;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\File;
use App\Support\Files\FileStorage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs one due account deletion request (ARCHITECTURE §13.8), in one transaction:
 *
 *   user scope          the user is anonymised, loses tokens and membership, and is soft-deleted
 *   organization scope  the same for every member, then the organization: status `deleted`,
 *                       name "Deleted organization", contact fields and files cleared, soft delete
 *
 * Legal and financial records (invoices, payments, competitions, offers, audit) are kept.
 * Finally `AccountDeleted` (one per deleted user).
 */
final readonly class ExecuteAccountDeletion
{
    /**
     * Stored name of a deleted organization. The API renders `identity.deleted_organization`.
     */
    public const string DELETED_ORGANIZATION_NAME = 'Deleted organization';

    public function __construct(
        private UserAnonymiser $anonymiser,
        private DeletionBlockers $blockers,
        private FileStorage $files,
    ) {}

    /**
     * @return bool true when the request was executed
     */
    public function handle(AccountDeletionRequest $request, ?Actor $actor = null): bool
    {
        $actor ??= Actor::system();

        return DB::transaction(function () use ($request, $actor): bool {
            /** @var AccountDeletionRequest $request */
            $request = AccountDeletionRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== DeletionStatus::Pending || $request->scheduled_for->greaterThan(Date::now())) {
                return false;
            }

            $organization = Organization::query()->withTrashed()->whereKey($request->organization_id)->lockForUpdate()->firstOrFail();

            if ($request->scope === DeletionScope::Organization && ($blockers = $this->blockers->for($organization)) !== []) {
                // CONTRACT-GAP: §13.8 checks blockers at request time only. Work that started
                // since then (a published competition, a joined one) postpones the deletion: the
                // request stays pending and is retried every hour.
                Log::warning('Account deletion postponed: the organization has open competitions.', [
                    'account_deletion_request_id' => $request->id,
                    'organization_id' => $organization->id,
                    'blockers' => count($blockers),
                ]);

                return false;
            }

            $events = $request->scope === DeletionScope::Organization
                ? $this->deleteOrganization($organization, $request->user_id)
                : $this->deleteUser($request->user_id, $organization->id, DeletionScope::User);

            $request->forceFill(['status' => DeletionStatus::Completed, 'completed_at' => Date::now()])->save();

            $requester = User::withTrashed()->find($request->user_id);

            AuditLogger::log('account_deletion.completed', $requester, meta: [
                'account_deletion_request_id' => $request->public_id,
                'scope' => $request->scope->value,
            ], actor: $actor, organizationId: $organization->id);

            foreach ($events as $event) {
                event($event);
            }

            return true;
        });
    }

    /**
     * @return list<AccountDeleted>
     */
    private function deleteUser(int $userId, int $organizationId, DeletionScope $scope): array
    {
        $user = User::query()->whereKey($userId)->lockForUpdate()->first();

        if ($user === null) {
            return [];
        }

        $this->anonymiser->anonymise($user);

        return [new AccountDeleted($user->id, $organizationId, $scope)];
    }

    /**
     * @return list<AccountDeleted>
     */
    private function deleteOrganization(Organization $organization, int $requesterId): array
    {
        $events = [];

        $memberIds = Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', '!=', $requesterId)
            ->orderBy('id')
            ->pluck('user_id');

        foreach ($memberIds as $memberId) {
            $events = [...$events, ...$this->deleteUser((int) $memberId, $organization->id, DeletionScope::User)];
        }

        // Other members' own pending requests are moot now.
        AccountDeletionRequest::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', '!=', $requesterId)
            ->where('status', DeletionStatus::Pending->value)
            ->update(['status' => DeletionStatus::Completed->value, 'completed_at' => Date::now()]);

        $events = [...$events, ...$this->deleteUser($requesterId, $organization->id, DeletionScope::Organization)];

        $files = File::query()->whereKey(array_filter([$organization->logo_file_id, $organization->profile_file_id]))->get();

        // CONTRACT-GAP: `email` and `phone` are NOT NULL, so "cleared" means an undeliverable
        // placeholder e-mail and an empty phone. The CR and VAT numbers stay: invoices reference
        // them.
        $organization->forceFill([
            'name' => self::DELETED_ORGANIZATION_NAME,
            'status' => OrganizationStatus::Deleted,
            'email' => UserAnonymiser::deletedEmail($organization->public_id),
            'phone' => '',
            'website' => null,
            'logo_file_id' => null,
            'profile_file_id' => null,
            'visible_in_suggestions' => false,
        ])->save();

        foreach ($files as $file) {
            $this->files->delete($file);
        }

        $organization->categories()->detach();
        $organization->delete();

        return $events;
    }
}
