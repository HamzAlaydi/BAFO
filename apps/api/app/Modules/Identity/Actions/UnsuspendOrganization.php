<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Services\OrganizationStatusMachine;
use App\Support\Auth\Actor;

/**
 * Admin panel (ARCHITECTURE §16): `suspended → active`.
 */
final readonly class UnsuspendOrganization
{
    public function __construct(private OrganizationStatusMachine $status) {}

    public function handle(Organization $organization, Actor $actor): Organization
    {
        return $this->status->transition($organization, OrganizationStatus::Active, $actor);
    }
}
