<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Data\RenderedMail;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\DisplayTime;
use App\Support\Money\Money;

/**
 * Renders the catalogue templates (`lang/{ar,en}/notifications.php`, ARCHITECTURE §11.2/§11.4)
 * from the stored parameters, in one language. In-app texts are rendered at read time in the
 * request language; push and mail in the recipient's language.
 *
 * Parameter rules:
 * - `direction` fills `:competition_type` from `competitions.direction.*` (lowercase in English);
 * - `close_time`, `cutoff_time` and `ends_at` (ISO-8601) are shown in Asia/Riyadh (CONVENTIONS §9.1);
 * - `amount_minor` fills `:amount` with the money format of CONVENTIONS §9.2;
 * - `{ar, en}` maps (plan names, close reasons) resolve to the language;
 * - a missing `reason` reads as «غير محدد» / "Not specified".
 */
final class NotificationRenderer
{
    /** @var list<string> */
    private const array TIME_PARAMS = ['close_time', 'cutoff_time', 'ends_at'];

    /**
     * @param  array<string, mixed>  $params
     */
    public function title(NotificationType $type, array $params, string $locale): string
    {
        return $this->translate('notifications.'.$type->key().'.title', $this->replacements($params, $locale), $locale);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function body(NotificationType $type, array $params, string $locale): string
    {
        $key = 'notifications.'.$type->key().'.body';
        $replace = $this->replacements($params, $locale);
        $countParam = $type->countParam();

        $body = $countParam !== null
            ? trans_choice($key, $this->count($params[$countParam] ?? null), $replace, $locale)
            : $this->translate($key, $replace, $locale);

        // The "Fees covered" line (§11.3 `competition.invited`, `invitation.joined`).
        $suffixKey = 'notifications.'.$type->key().'.body_sponsored_suffix';

        if (($params['sponsored'] ?? false) === true && __($suffixKey, [], $locale) !== $suffixKey) {
            $body .= $this->translate($suffixKey, [], $locale);
        }

        return $body;
    }

    /**
     * The mail texts; only the types whose catalogue row has mail define them.
     *
     * @param  array<string, mixed>  $params
     */
    public function mail(NotificationType $type, array $params, string $locale): RenderedMail
    {
        $prefix = 'notifications.'.$type->key().'.';
        $replace = $this->replacements($params, $locale);

        return new RenderedMail(
            subject: $this->translate($prefix.'mail_subject', $replace, $locale),
            intro: $this->translate($prefix.'mail_intro', $replace, $locale),
            action: $this->translate($prefix.'mail_action', $replace, $locale),
        );
    }

    /**
     * The `:placeholder` values of the parameters in one language.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    public function replacements(array $params, string $locale): array
    {
        $replace = ['reason' => $this->translate('notifications.reason_unspecified', [], $locale)];

        foreach ($params as $name => $value) {
            if ($name === 'direction' && is_string($value)) {
                $replace['competition_type'] = $this->competitionType($value, $locale);
            }

            if ($name === 'amount_minor' && is_int($value)) {
                $replace['amount'] = Money::format($value, $locale);
            }

            $text = $this->text($value, $locale);

            if ($text === null) {
                continue;
            }

            $replace[$name] = in_array($name, self::TIME_PARAMS, true) ? DisplayTime::formatIso($text, $locale) : $text;
        }

        return $replace;
    }

    /**
     * «مناقصة» / «مزايدة»; "tender" / "auction" (lowercase inside English sentences, §11.4).
     */
    private function competitionType(string $direction, string $locale): string
    {
        $word = $this->translate('competitions.direction.'.$direction, [], $locale);

        if ($word === 'competitions.direction.'.$direction) {
            $word = $direction;
        }

        return $locale === 'ar' ? $word : mb_strtolower($word);
    }

    private function text(mixed $value, string $locale): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_array($value) => $this->pick($value, $locale),
            default => null, // booleans and nulls are flags, not placeholders
        };
    }

    /**
     * @param  array<mixed>  $translations
     */
    private function pick(array $translations, string $locale): ?string
    {
        $text = $translations[$locale] ?? $translations['ar'] ?? $translations['en'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    private function count(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function translate(string $key, array $replace, string $locale): string
    {
        $line = __($key, $replace, $locale);

        return is_string($line) ? $line : $key;
    }
}
