<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

/**
 * Typed access to `lang/{ar,en}/admin.php` for panel labels.
 */
final class Lang
{
    /**
     * @param  array<string, scalar>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $text = __('admin.'.$key, $replace);

        return is_string($text) ? $text : $key;
    }
}
