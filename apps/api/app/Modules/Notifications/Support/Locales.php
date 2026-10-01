<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * The two languages of BAFO (BRIEF): Arabic (default) and English.
 */
final class Locales
{
    /** @var list<string> */
    public const array SUPPORTED = ['ar', 'en'];

    /**
     * The current application language, reduced to `ar` or `en` (Arabic when unsupported).
     */
    public static function current(): string
    {
        return self::normalize(app()->getLocale());
    }

    public static function normalize(?string $locale): string
    {
        $language = strtolower(substr((string) $locale, 0, 2));

        return in_array($language, self::SUPPORTED, true) ? $language : 'ar';
    }
}
