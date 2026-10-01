<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Reusable form fields of the panel.
 */
final class Fields
{
    /**
     * Arabic and English inputs for a `{"ar": …, "en": …}` JSON attribute (§5.0 translated names).
     */
    public static function translated(string $name, string $label, bool $required = true, bool $multiline = false, int $maxLength = 255): Grid
    {
        $input = static fn (string $locale) => ($multiline ? Textarea::make("{$name}.{$locale}")->rows(3) : TextInput::make("{$name}.{$locale}"))
            ->label($label.' ('.Lang::get('common.locale_'.$locale).')')
            ->required($required)
            ->maxLength($maxLength);

        return Grid::make(2)->schema([$input('ar'), $input('en')]);
    }

    /**
     * A SAR amount typed with up to 2 decimals and stored as integer halalas (CONVENTIONS §9.2:
     * never a float).
     */
    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->suffix(Lang::get('common.sar'))
            ->inputMode('decimal')
            ->regex('/^\d{1,13}(\.\d{1,2})?$/')
            ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? self::toDecimal($state) : (is_string($state) ? $state : null))
            ->dehydrateStateUsing(static fn (mixed $state): ?int => self::toMinor($state));
    }

    /**
     * A close reason of one kind (§5.2 `close_reasons`, active only) and its note, which is
     * required when the chosen reason `requires_note`. The module Action re-checks both.
     *
     * @return array{0: Select, 1: Textarea}
     */
    public static function closeReason(CloseReasonKind $kind, string $name = 'reason_id'): array
    {
        return [
            Select::make($name)
                ->label(Lang::get('fields.reason'))
                ->required()
                ->live()
                ->options(static fn (): array => CloseReason::query()
                    ->where('kind', $kind->value)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(static fn (CloseReason $reason): array => [$reason->id => $reason->translated('name') ?? $reason->code])
                    ->all()),
            Textarea::make('note')
                ->label(Lang::get('fields.note'))
                ->maxLength(1000)
                ->required(static fn (Get $get): bool => CloseReason::query()->whereKey($get($name))->value('requires_note') === true),
        ];
    }

    public static function toMinor(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        if (! is_string($value) || preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/', trim($value), $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1]) * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    public static function toDecimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
