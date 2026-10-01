<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Data;

use App\Modules\Integrations\Models\WebhookEndpoint;

/**
 * An endpoint together with its plain `whsec_` signing secret, which is shown once (create, rotate).
 */
final readonly class IssuedWebhookEndpoint
{
    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $secret,
    ) {}
}
