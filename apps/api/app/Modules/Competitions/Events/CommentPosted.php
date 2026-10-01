<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Events;

use App\Modules\Competitions\Models\Comment;
use App\Support\Auth\Actor;

/**
 * A Q&A question, reply or announcement (ARCHITECTURE §10).
 */
final readonly class CommentPosted
{
    public function __construct(
        public Comment $comment,
        public Actor $actor,
    ) {}
}
