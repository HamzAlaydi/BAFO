<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Seeders;

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Settings\AppSetting;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Platform reference data (ARCHITECTURE §17), idempotent:
 *
 * - one app_settings row per registered default (every module's §15.3 keys) that has no row
 *   yet, so the admin settings page lists them; existing values are never overwritten;
 * - a placeholder legal document for every code in AR and EN, version 2026-10-01, published
 *   at seed time so consent capture works. The body starts with a draft notice in the
 *   document's language (`platform.legal.draft_notice`): «مسودة: …» in Arabic, "Draft text –
 *   counsel to provide" in English, so an Arabic document never opens with an English line.
 */
final class PlatformReferenceSeeder extends Seeder
{
    public const string LEGAL_VERSION = '2026-10-01';

    /**
     * The English draft notice, also the fallback when the notice key is missing.
     */
    public const string DRAFT_MARKER = 'Draft text – counsel to provide';

    public function run(Settings $settings): void
    {
        foreach ($settings->registeredDefaults() as $key => $value) {
            AppSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $settings->flush();

        foreach (LegalDocumentCode::cases() as $code) {
            foreach (['ar', 'en'] as $locale) {
                LegalDocument::query()->firstOrCreate(
                    ['code' => $code->value, 'locale' => $locale, 'version' => self::LEGAL_VERSION],
                    [
                        'title' => $code->label($locale),
                        'body_markdown' => self::placeholderBody($code, $locale),
                        'published_at' => Date::now(),
                    ],
                );
            }
        }
    }

    /**
     * The first line of a placeholder body, in the document's language.
     */
    public static function draftNotice(string $locale): string
    {
        $notice = __('platform.legal.draft_notice', [], $locale);

        return is_string($notice) && $notice !== 'platform.legal.draft_notice' ? $notice : self::DRAFT_MARKER;
    }

    private static function placeholderBody(LegalDocumentCode $code, string $locale): string
    {
        $note = __('platform.legal.placeholder_body', [], $locale);

        return self::draftNotice($locale).".\n\n# ".$code->label($locale)."\n\n".(is_string($note) ? $note : '')."\n";
    }
}
