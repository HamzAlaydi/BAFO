<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `sponsorship.publish_failed` (ARCHITECTURE §11.3): payment received, publish refused. The
 * mail adds the reason when the refusal code has a message in `competitions.errors.*`.
 */
final class SponsorshipPublishFailedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::SponsorshipPublishFailed;
    }

    protected function mailLines(string $locale): array
    {
        $code = $this->params['error_code'] ?? null;

        if (! is_string($code) || $code === '') {
            return [];
        }

        $reason = self::optionalLine('competitions.errors.'.$code, [], $locale)
            ?? self::optionalLine('billing.errors.'.$code, [], $locale)
            ?? self::optionalLine('errors.'.$code, [], $locale);

        return $reason === null
            ? []
            : [self::line('notifications.sponsorship_publish_failed.mail_reason', ['reason' => $reason], $locale)];
    }
}
