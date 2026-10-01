<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Services\OrganizationStatusMachine;
use App\Support\Auth\Actor;

/**
 * Admin panel (ARCHITECTURE §16): `active → suspended` with a reason. The users are stopped by
 * the account gate (403 `organization_suspended`); published competitions keep running.
 */
final readonly class SuspendOrganization
{
    public function __construct(private OrganizationStatusMachine $status) {}

    public function handle(Organization $organization, string $reason, Actor $actor): Organization
    {
        return $this->status->transition($organization, OrganizationStatus::Suspended, $actor, $reason);
    }
}
