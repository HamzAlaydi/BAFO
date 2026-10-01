<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;

/**
 * Content or schedule of a competition changed (ARCHITECTURE §10). `$fields` uses the realtime names:
 * title, description, schedule, attachments, invitations (API.md §5), plus the other edited column
 * groups (category, region, rules) on drafts.
 */
final readonly class CompetitionUpdated
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        public Competition $competition,
        public array $fields,
        public Actor $actor,
    ) {}
}
