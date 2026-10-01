<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Enums\DeletionScope;

/**
 * An account deletion request was executed (ARCHITECTURE §10, §13.8). Notifications removes the
 * device tokens; Integrations revokes the organization's API access (organization scope).
 *
 * One event per deleted user. `$organizationId` is the organization the user belonged to. For an
 * organization-scope request, every other member is dispatched with scope `user` and the
 * requesting owner last with scope `organization`, so organization-level listeners run once.
 */
final readonly class AccountDeleted
{
    public function __construct(
        public int $userId,
        public ?int $organizationId,
        public DeletionScope $scope,
    ) {}
}
