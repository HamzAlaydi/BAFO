<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;

/**
 * An organization with one user per role, plus an inactive member (NotificationScenario::team()).
 */
final readonly class Team
{
    public function __construct(
        public Organization $organization,
        public User $owner,
        public User $admin,
        public User $member,
        public User $inactive,
    ) {}

    /**
     * The active users.
     *
     * @return list<User>
     */
    public function active(): array
    {
        return [$this->owner, $this->admin, $this->member];
    }

    /**
     * The active users with `billing.view` and `integrations.manage` (owner and admin).
     *
     * @return list<User>
     */
    public function managers(): array
    {
        return [$this->owner, $this->admin];
    }
}
