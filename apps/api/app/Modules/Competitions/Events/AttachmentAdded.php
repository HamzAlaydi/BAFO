<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Auth\Actor;

/**
 * A document or link was added to a competition (ARCHITECTURE §10). After publish it is an addendum.
 */
final readonly class AttachmentAdded
{
    public function __construct(
        public CompetitionAttachment $attachment,
        public Actor $actor,
    ) {}
}
