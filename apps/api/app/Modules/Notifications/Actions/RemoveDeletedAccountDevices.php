<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Support\Facades\DB;

/**
 * Removes the push registrations of a deleted account (ARCHITECTURE §13.8: "Device tokens are
 * removed by the Notifications listener on `AccountDeleted`").
 *
 * Identity dispatches `AccountDeleted` once per deleted user. As a safety net (a lost event, a
 * user soft-deleted by other means), the tokens of every soft-deleted user are removed too.
 */
final class RemoveDeletedAccountDevices
{
    /**
     * @return int the number of device tokens removed
     */
    public function handle(int $userId): int
    {
        return DB::transaction(static fn (): int => DeviceToken::query()
            ->where('user_id', $userId)
            ->orWhereIn('user_id', User::onlyTrashed()->select('id'))
            ->delete());
    }
}
