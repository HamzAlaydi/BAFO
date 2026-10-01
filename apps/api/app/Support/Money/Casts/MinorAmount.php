<?php

declare(strict_types=1);

namespace App\Support\Money\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Integer halalas for a `*_minor` bigint column. Reads return int (or null); writes accept
 * int or an integral numeric string and reject floats and fractions, so money never turns
 * into a float on its way into the database.
 *
 *     protected function casts(): array { return ['amount_minor' => MinorAmount::class]; }
 *
 * @implements CastsAttributes<int|null, int|string|null>
 */
final class MinorAmount implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : self::toInt($value, $key);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : self::toInt($value, $key);
    }

    public static function toInt(mixed $value, string $key = 'amount'): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException("The money attribute [{$key}] must be an integer amount of halalas.");
    }
}
