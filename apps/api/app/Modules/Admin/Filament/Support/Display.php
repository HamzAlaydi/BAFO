<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Support\Money\Money;
use BackedEnum;
use Carbon\CarbonInterface;

/**
 * Display formatting for the panel, in the panel language (CONVENTIONS §9): money with the
 * ر.س / SAR label, `{ar, en}` names, enum labels, Riyadh times and status colours.
 */
final class Display
{
    public const string TIMEZONE = 'Asia/Riyadh';

    public const string DATE_TIME = 'Y-m-d H:i';

    /** Machine-readable times (the offer ledger), CONVENTIONS §9.1. */
    public const string PRECISE = 'Y-m-d H:i:s.v';

    /**
     * Status values shown green, amber or red. Everything else is grey.
     *
     * @var array<string, list<string>>
     */
    private const array COLORS = [
        'success' => ['active', 'succeeded', 'cleared', 'reported', 'awarded', 'joined', 'issued', 'published', 'completed', 'ready', 'synced', 'settled', 'live', 'running'],
        'warning' => ['pending', 'pending_payment', 'pending_verification', 'scheduled', 'draft', 'sent', 'viewed', 'invited', 'closed', 'bafo_round', 'new', 'reserved', 'processing', 'queued', 'upcoming'],
        'danger' => ['failed', 'rejected', 'cancelled', 'suspended', 'revoked', 'expired', 'declined', 'disabled', 'deleted', 'inactive', 'refunded', 'void', 'not_awarded'],
    ];

    public static function money(?int $minor): ?string
    {
        return $minor === null ? null : Money::format($minor, app()->getLocale());
    }

    /**
     * A `{"ar": …, "en": …}` value in the panel language, falling back to the other one.
     */
    public static function translated(mixed $value): ?string
    {
        if (! is_array($value)) {
            return is_string($value) ? $value : null;
        }

        $locale = app()->getLocale();
        $text = $value[$locale] ?? $value['ar'] ?? $value['en'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * The label of a module enum (every module enum has `label()`), or the raw value.
     */
    public static function enum(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            if (method_exists($state, 'label')) {
                $label = $state->label();

                return is_string($label) ? $label : (string) $state->value;
            }

            return (string) $state->value;
        }

        return is_scalar($state) ? (string) $state : null;
    }

    /**
     * Select options for a module enum: value => label.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    public static function options(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = self::enum($case) ?? (string) $case->value;
        }

        return $options;
    }

    public static function color(mixed $state): string
    {
        $value = $state instanceof BackedEnum ? (string) $state->value : (is_scalar($state) ? (string) $state : '');

        foreach (self::COLORS as $color => $values) {
            if (in_array($value, $values, true)) {
                return $color;
            }
        }

        return 'gray';
    }

    public static function dateTime(?CarbonInterface $time, string $format = self::DATE_TIME): ?string
    {
        return $time?->copy()->setTimezone(self::TIMEZONE)->format($format);
    }

    public static function yesNo(?bool $value): string
    {
        return Lang::get($value === true ? 'common.yes' : 'common.no');
    }
}
