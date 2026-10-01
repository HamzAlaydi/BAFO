<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\Webhooks;

use App\Modules\Integrations\Services\Webhooks\BlockedWebhookTarget;
use App\Modules\Integrations\Services\Webhooks\WebhookUrlGuard;
use App\Support\Exceptions\ApiException;

/**
 * Runs the SSRF guard on create and update (ARCHITECTURE §14.6): a violation is 422
 * `webhook_url_invalid`, with the message on the `url` field and `details.reason`.
 */
trait ValidatesWebhookUrl
{
    private function assertWebhookUrl(WebhookUrlGuard $guard, string $url): void
    {
        try {
            $guard->check($url);
        } catch (BlockedWebhookTarget $blocked) {
            $message = __('integrations.errors.webhook_url_invalid');

            throw new ApiException(
                errorCode: 'webhook_url_invalid',
                messageKey: 'integrations.errors.webhook_url_invalid',
                status: 422,
                errors: ['url' => [is_string($message) ? $message : 'webhook_url_invalid']],
                details: ['reason' => $blocked->reason],
            );
        }
    }
}
