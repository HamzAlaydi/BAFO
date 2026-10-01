<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

/**
 * The localised mail texts of a notification type (`mail_subject`, `mail_intro`, `mail_action`).
 */
final readonly class RenderedMail
{
    public function __construct(
        public string $subject,
        public string $intro,
        public string $action,
    ) {}
}
