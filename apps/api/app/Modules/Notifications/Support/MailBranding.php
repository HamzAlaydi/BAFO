<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * Values the shared mail theme (`resources/views/vendor/mail/**`) reads. Every markdown mail
 * uses the theme, including the token-carrying mails of Identity and Competitions (§11.5).
 */
final class MailBranding
{
    /**
     * The file behind the logo URL (the brand mark, 112 px, from `assets/brand`).
     */
    public static function logoFile(): string
    {
        return dirname(__DIR__).'/Resources/brand/bafo-mark.png';
    }

    /**
     * Absolute URL of the brand mark. Mail clients need an absolute URL; it is built from
     * APP_URL, never from the current request, so queued mails get the same link.
     */
    public static function logoUrl(): string
    {
        $base = config('app.url');
        $path = config('bafo.notifications.mail.logo_path', 'mail/brand/bafo-mark.png');

        return rtrim(is_string($base) ? $base : '', '/').'/'.ltrim(is_string($path) ? $path : '', '/');
    }

    /**
     * The link of the header logo: the web app in the mail's language.
     */
    public static function homeUrl(): string
    {
        $base = config('bafo.platform.web_url');

        return rtrim(is_string($base) ? $base : '', '/').'/'.self::locale();
    }

    public static function isRtl(): bool
    {
        return self::locale() === 'ar';
    }

    public static function direction(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /**
     * The start side of the text: `right` in Arabic, `left` otherwise (for inline styles; mail
     * clients do not all support `text-align: start`).
     */
    public static function startSide(): string
    {
        return self::isRtl() ? 'right' : 'left';
    }

    public static function locale(): string
    {
        return Locales::current();
    }
}
