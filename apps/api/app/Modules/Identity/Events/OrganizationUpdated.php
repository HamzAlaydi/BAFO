<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;

/**
 * The organization profile changed (ARCHITECTURE §10).
 */
final readonly class OrganizationUpdated
{
    /**
     * @param  list<string>  $changedFields  column names, plus `category_ids`, `logo_file_id`, `profile_file_id`
     */
    public function __construct(
        public Organization $organization,
        public array $changedFields,
        public Actor $actor,
    ) {}
}
