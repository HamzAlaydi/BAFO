<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `award.won` (ARCHITECTURE §11.3). The mail includes the issuer's `message_to_winner`.
 */
final class AwardWonNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::AwardWon;
    }

    protected function mailLines(string $locale): array
    {
        $message = $this->params['message_to_winner'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            return [];
        }

        return [
            self::line('notifications.award_won.mail_message_label', [], $locale),
            trim($message),
        ];
    }
}
