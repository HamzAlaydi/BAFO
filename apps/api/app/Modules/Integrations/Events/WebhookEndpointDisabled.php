<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Events;

use App\Modules\Integrations\Models\WebhookEndpoint;

/**
 * An endpoint was disabled automatically (`failing`): 410 Gone, or five days of continuous
 * failure (ARCHITECTURE §10, §14.5). Notifications e-mails the organization's integration users.
 */
final readonly class WebhookEndpointDisabled
{
    public function __construct(
        public WebhookEndpoint $endpoint,
    ) {}
}
