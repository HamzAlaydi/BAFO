<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use Illuminate\Support\Facades\Session;

/**
 * The panel language (ARCHITECTURE §16): Arabic (RTL) by default, English on request. The choice
 * is kept in the admin's session; Filament switches the text direction with the locale.
 */
final class AdminLocale
{
    public const string SESSION_KEY = 'bafo_admin_locale';

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        /** @var list<string> $locales */
        $locales = config('bafo.admin.locales', ['ar', 'en']);

        return $locales;
    }

    public static function default(): string
    {
        $default = config('bafo.admin.default_locale', 'ar');

        return is_string($default) && in_array($default, self::supported(), true) ? $default : 'ar';
    }

    public static function current(): string
    {
        $locale = Session::get(self::SESSION_KEY);

        return is_string($locale) && in_array($locale, self::supported(), true) ? $locale : self::default();
    }

    public static function set(string $locale): void
    {
        if (in_array($locale, self::supported(), true)) {
            Session::put(self::SESSION_KEY, $locale);
        }
    }

    /**
     * The locale the toggle switches to.
     */
    public static function other(): string
    {
        return self::current() === 'ar' ? 'en' : 'ar';
    }
}
