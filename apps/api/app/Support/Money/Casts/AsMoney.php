<?php

declare(strict_types=1);

namespace App\Support\Money\Casts;

use App\Support\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a `*_minor` bigint column to the Money value object. Writes accept Money or int.
 *
 *     protected function casts(): array { return ['price_minor' => AsMoney::class]; }
 *
 * @implements CastsAttributes<Money|null, Money|int|null>
 */
final class AsMoney implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::of(MinorAmount::toInt($value, $key));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->amountMinor,
            default => MinorAmount::toInt($value, $key),
        };
    }
}
