<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

/**
 * The recipient-independent part of a notification (ARCHITECTURE §11.2): the template
 * parameters, the subject and the locale-free client route.
 *
 * Parameters hold raw values; the renderer localises them per locale (`direction` →
 * `:competition_type`, ISO times → Riyadh display time, `{ar, en}` maps → one language,
 * `amount_minor` → `:amount`).
 */
final readonly class NotificationPayload
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public array $params,
        public NotificationSubject $subject,
        public string $route,
    ) {}
}
