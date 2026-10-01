<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationType;

/**
 * `invoice.issued` (ARCHITECTURE §11.3). The PDF is downloaded from the dashboard; the mail has
 * no attachment.
 */
final class InvoiceIssuedNotification extends BafoNotification
{
    public static function type(): NotificationType
    {
        return NotificationType::InvoiceIssued;
    }

    protected function mailLines(string $locale): array
    {
        return [self::line('notifications.invoice_issued.mail_download_hint', [], $locale)];
    }
}
