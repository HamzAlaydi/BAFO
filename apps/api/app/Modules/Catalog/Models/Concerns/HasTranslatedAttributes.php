<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads `{"ar": "…", "en": "…"}` JSONB attributes (ARCHITECTURE §5.0 "Translated names"), cast
 * to `array` on the model. App v1 renders the request locale; public v1 returns the whole object.
 *
 * @mixin Model
 */
trait HasTranslatedAttributes
{
    /**
     * The attribute in `$locale` (default: the app locale), falling back to Arabic, then English.
     */
    public function translated(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->getAttribute($attribute);

        if (! is_array($value)) {
            return null;
        }

        $text = $value[$locale ?? app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? null;

        return is_string($text) ? $text : null;
    }
}
