<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Competitions\Models\Competition;

/**
 * The locale-free client routes of notifications (ARCHITECTURE §11.1, CONVENTIONS §4.3) and
 * their web URLs for mail buttons.
 */
final class NotificationRoutes
{
    public const string BILLING = '/billing';

    public const string INTEGRATIONS = '/integrations';

    public const string NOTIFICATIONS = '/notifications';

    public static function competition(Competition $competition): string
    {
        return '/competitions/'.$competition->public_id;
    }

    public static function competitionLive(Competition $competition): string
    {
        return self::competition($competition).'/live';
    }

    public static function competitionQa(Competition $competition): string
    {
        return self::competition($competition).'/qa';
    }

    public static function invoice(Invoice $invoice): string
    {
        return self::BILLING.'/invoices/'.$invoice->public_id;
    }

    /**
     * The web page of a route (CONVENTIONS §4.3): `{WEB_URL}/{locale}/dashboard{route}`.
     */
    public static function webUrl(string $route, string $locale): string
    {
        $base = config('bafo.platform.web_url');

        return rtrim(is_string($base) ? $base : '', '/').'/'.$locale.'/dashboard'.$route;
    }
}
