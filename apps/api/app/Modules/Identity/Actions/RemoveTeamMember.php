<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\MemberRemoved;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserAnonymiser;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /team/members/{membership}` (ARCHITECTURE §13.10): not the owner and not yourself.
 * Deletes the membership, anonymises and soft-deletes the user and revokes their tokens. The
 * member's competitions stay with the organization.
 */
final readonly class RemoveTeamMember
{
    public function __construct(private UserAnonymiser $anonymiser) {}

    public function handle(Membership $membership, User $editor, Actor $actor): void
    {
        DB::transaction(function () use ($membership, $editor, $actor): void {
            /** @var Membership $membership */
            $membership = Membership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            UpdateTeamMember::assertEditable($membership, $editor);

            $user = User::query()->whereKey($membership->user_id)->lockForUpdate()->firstOrFail();

            AuditLogger::log('member.removed', $membership, meta: [
                'user_id' => $user->public_id,
                'role' => $membership->role->value,
            ], actor: $actor, organizationId: $membership->organization_id);

            $this->anonymiser->anonymise($user);

            event(new MemberRemoved($membership, $actor));
        });
    }
}
