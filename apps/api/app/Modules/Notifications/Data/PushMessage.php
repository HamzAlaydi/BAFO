<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

/**
 * A push notification, rendered in the recipient's language (ARCHITECTURE §11.1). It never
 * carries offer amounts, ranks or other participants' identities.
 *
 * `data` is the deep-link payload, all strings: `{type, notification_id, subject_type,
 * subject_id, route}`.
 */
final readonly class PushMessage
{
    /**
     * @param  array{type: string, notification_id: string, subject_type: string, subject_id: string, route: string}  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data,
    ) {}
}
