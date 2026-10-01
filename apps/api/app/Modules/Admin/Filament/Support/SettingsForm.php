<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Platform\Actions\UpdateAppSetting;
use App\Support\Auth\Actor;
use App\Support\Settings\Settings;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Lang as Translator;

/**
 * The §15.3 settings page form, built from the defaults the modules registered (`Settings`): one
 * field per key, typed by its default value (switch, integer, SAR amount for `*_minor`, text,
 * a group of fields for objects, a tag list for lists). Saving calls Platform's `UpdateAppSetting`
 * for each changed key only.
 */
final class SettingsForm
{
    /** Section order; any other prefix follows. */
    private const array GROUPS = ['app', 'competitions', 'bidding', 'billing', 'sponsorship'];

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->settings->registeredDefaults();
    }

    /**
     * The form state: current values keyed by field name.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = [];

        foreach ($this->defaults() as $key => $default) {
            $value = $this->settings->get($key, $default);
            $state[self::field($key)] = is_array($default) && array_is_list($default) && is_array($value)
                ? array_map(static fn (mixed $item): string => (string) $item, $value)
                : $value;
        }

        return $state;
    }

    /**
     * @return list<Section>
     */
    public function components(): array
    {
        $groups = [];

        foreach ($this->defaults() as $key => $default) {
            $groups[strstr($key, '.', true) ?: $key][] = $this->component($key, $default);
        }

        uksort($groups, static function (string $a, string $b): int {
            $order = array_flip(self::GROUPS);

            return ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b);
        });

        $sections = [];

        foreach ($groups as $group => $components) {
            $sections[] = Section::make(self::text('settings.groups.'.$group, $group))->columns(2)->collapsible()->schema($components);
        }

        return $sections;
    }

    /**
     * Saves the changed keys through Platform's UpdateAppSetting. Returns the number changed.
     *
     * @param  array<string, mixed>  $state
     */
    public function save(array $state, Actor $actor): int
    {
        $changed = 0;

        foreach ($this->defaults() as $key => $default) {
            $field = self::field($key);

            if (! array_key_exists($field, $state)) {
                continue;
            }

            $value = self::cast($state[$field], $default);

            if ($value === $this->settings->get($key, $default)) {
                continue;
            }

            app(UpdateAppSetting::class)->handle($key, $value, $actor);
            $changed++;
        }

        return $changed;
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * Casts a submitted value to the shape of the key's default.
     */
    public static function cast(mixed $value, mixed $default): mixed
    {
        return match (true) {
            is_bool($default) => (bool) $value,
            is_int($default) => is_numeric($value) ? (int) $value : $default,
            is_array($default) && array_is_list($default) => array_values(array_map(
                static fn (mixed $item): mixed => self::cast($item, $default[0] ?? ''),
                array_filter(is_array($value) ? $value : [], static fn (mixed $item): bool => $item !== null && $item !== ''),
            )),
            is_array($default) => self::castObject(is_array($value) ? $value : [], $default),
            default => is_scalar($value) ? trim((string) $value) : '',
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<array-key, mixed>  $default
     * @return array<array-key, mixed>
     */
    private static function castObject(array $value, array $default): array
    {
        $object = [];

        foreach ($default as $subKey => $subDefault) {
            $object[$subKey] = self::cast($value[$subKey] ?? $subDefault, $subDefault);
        }

        return $object;
    }

    private function component(string $key, mixed $default): Field|Fieldset
    {
        $name = self::field($key);
        $label = self::text('settings.keys.'.str_replace('.', '_', $key), $key);

        if (is_array($default) && ! array_is_list($default)) {
            return Fieldset::make($label)->columns(min(3, count($default)))->columnSpanFull()->schema(array_map(
                fn (string $subKey): Field => $this->scalarField($name.'.'.$subKey, self::text('settings.sub.'.$subKey, $subKey), $subKey, $default[$subKey]),
                array_map('strval', array_keys($default)),
            ));
        }

        if (is_array($default)) {
            return TagsInput::make($name)->label($label)->helperText($key)
                ->nestedRecursiveRules(is_int($default[0] ?? null) ? ['integer', 'min:0'] : ['string', 'max:100']);
        }

        return $this->scalarField($name, $label, $key, $default)->helperText($key);
    }

    private function scalarField(string $name, string $label, string $key, mixed $default): Field
    {
        return match (true) {
            is_bool($default) => Toggle::make($name)->label($label),
            is_int($default) && str_ends_with($key, '_minor') => Fields::money($name, $label)->required(),
            is_int($default) => TextInput::make($name)->label($label)->required()->integer()->minValue(0),
            default => TextInput::make($name)->label($label)->maxLength(500),
        };
    }

    private static function text(string $key, string $fallback): string
    {
        return Translator::has('admin.'.$key) ? Lang::get($key) : $fallback;
    }
}
