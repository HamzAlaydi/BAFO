<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /account/deletion` (ARCHITECTURE §13.8): the pending request becomes `cancelled`.
 *
 * CONTRACT-GAP: API.md gives no error for "nothing to cancel"; it is 404 `not_found`.
 */
final class CancelAccountDeletion
{
    public function handle(User $user, Actor $actor): void
    {
        DB::transaction(static function () use ($user, $actor): void {
            $request = AccountDeletionRequest::query()
                ->where('user_id', $user->id)
                ->where('status', DeletionStatus::Pending->value)
                ->lockForUpdate()
                ->first()
                ?? throw (new ModelNotFoundException)->setModel(AccountDeletionRequest::class);

            $request->forceFill(['status' => DeletionStatus::Cancelled, 'cancelled_at' => Date::now()])->save();

            AuditLogger::log('account_deletion.cancelled', $user, meta: [
                'account_deletion_request_id' => $request->public_id,
            ], actor: $actor, organizationId: $request->organization_id);
        });
    }
}
