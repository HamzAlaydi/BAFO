<?php

declare(strict_types=1);

namespace App\Support\Features;

/**
 * The release scope of the product (RELEASE_SCOPE.md §1.1): the admin-editable setting
 * `platform.release_scope`. `core` shows the tender/auction core only; `full` brings every
 * feature back. Clients never read the scope itself: they read the flags it derives
 * (FeatureFlags::all()).
 */
enum ReleaseScope: string
{
    case Core = 'core';
    case Full = 'full';

    /**
     * The stored setting as a scope. Anything that is not a known value reads as `core`, so a
     * broken row can never widen the release.
     */
    public static function fromSetting(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Core) : self::Core;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }

    public function isFull(): bool
    {
        return $this === self::Full;
    }

    public function label(?string $locale = null): string
    {
        $label = __('platform.enums.release_scope.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
