<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

use RuntimeException;

/**
 * Raised by WebhookUrlGuard. `$reason` is a machine word: `scheme`, `credentials`, `port`,
 * `host`, `unresolvable` or `private_address`.
 */
final class BlockedWebhookTarget extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Webhook target blocked: '.$reason);
    }
}
