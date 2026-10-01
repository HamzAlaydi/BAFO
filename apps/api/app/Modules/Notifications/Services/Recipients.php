<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * User-level recipient sets resolved from organizations (ARCHITECTURE §11.1): the active users
 * with an active membership. Billing users hold `billing.view`, integration users hold
 * `integrations.manage` (§8.1).
 */
final class Recipients
{
    /**
     * @return Collection<int, User>
     */
    public function members(int $organizationId): Collection
    {
        return $this->membersByOrganization([$organizationId])[$organizationId] ?? new Collection;
    }

    /**
     * @return Collection<int, User>
     */
    public function membersWith(int $organizationId, Permission $permission): Collection
    {
        return $this->members($organizationId)
            ->filter(static fn (User $user): bool => $user->hasPermission($permission))
            ->values();
    }

    /**
     * One query for several organizations.
     *
     * @param  list<int>  $organizationIds
     * @return array<int, Collection<int, User>> keyed by organization id (organizations without users are absent)
     */
    public function membersByOrganization(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        $users = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('membership', static function (Builder $membership) use ($organizationIds): void {
                $membership->whereIn('organization_id', array_values(array_unique($organizationIds)))
                    ->where('status', MembershipStatus::Active->value);
            })
            ->with('membership')
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($users as $user) {
            $organizationId = $user->membership?->organization_id;

            if ($organizationId !== null) {
                $grouped[$organizationId] ??= new Collection;
                $grouped[$organizationId]->push($user);
            }
        }

        return $grouped;
    }

    /**
     * A single user (the paying user, the job creator), if still active.
     */
    public function user(?int $userId): ?User
    {
        if ($userId === null) {
            return null;
        }

        return User::query()
            ->whereKey($userId)
            ->where('status', UserStatus::Active->value)
            ->first();
    }
}
