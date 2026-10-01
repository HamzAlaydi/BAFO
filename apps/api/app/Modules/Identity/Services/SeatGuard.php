<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\Organization;
use App\Support\Exceptions\ApiException;

/**
 * The seat rule of ARCHITECTURE §13.2: adding or reactivating a member when usage (`invited` +
 * `active` memberships) ≥ `AccessPolicy::seatLimit()` is 409 `seat_limit_reached`. When a plan
 * lapses, existing members keep working; only adding is blocked.
 */
final readonly class SeatGuard
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * Locks the organization row, which serialises concurrent seat changes. Call inside a
     * transaction.
     */
    public function lock(Organization $organization): Organization
    {
        /** @var Organization */
        return Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @throws ApiException `seat_limit_reached` (409, `details.seats = {used, total}`)
     */
    public function assertFreeSeat(Organization $organization): void
    {
        $used = $this->entitlements->seatsUsed($organization);
        $total = $this->entitlements->seatLimit($organization);

        if ($used >= $total) {
            throw IdentityError::make('seat_limit_reached', ['seats' => ['used' => $used, 'total' => $total]]);
        }
    }

    public function lockAndAssertFreeSeat(Organization $organization): Organization
    {
        $organization = $this->lock($organization);
        $this->assertFreeSeat($organization);

        return $organization;
    }
}
