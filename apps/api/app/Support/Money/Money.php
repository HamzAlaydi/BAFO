<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;

/**
 * SAR money in integer halalas (ARCHITECTURE §4.4, BRIEF "Money"). Never a float.
 *
 * Static helpers (the contract):
 *   Money::vat(11500)            VAT at 15%, rounded half up        → 1725
 *   Money::ceilTo(9751, 50)      round up to a multiple             → 9800
 *   Money::bps(250, 10000)       part of whole in basis points      → 250
 *   Money::format(1250000, 'ar') display string (CONVENTIONS §9.2)  → "12,500.00 ر.س"
 *
 * Value object (optional, e.g. with the AsMoney cast):
 *   Money::of(1250000)->plus(Money::of(100))->toArray()  → {amount_minor: 1250100, currency: "SAR"}
 */
final readonly class Money implements JsonSerializable
{
    public const string CURRENCY = 'SAR';

    public const int DEFAULT_VAT_RATE_BP = 1500;

    public function __construct(public int $amountMinor) {}

    public static function of(int $amountMinor): self
    {
        return new self($amountMinor);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->amountMinor + $other->amountMinor);
    }

    public function minus(self $other): self
    {
        return new self($this->amountMinor - $other->amountMinor);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    public function vatAmount(int $rateBp = self::DEFAULT_VAT_RATE_BP): self
    {
        return new self(self::vat($this->amountMinor, $rateBp));
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor;
    }

    public function formatted(string $locale): string
    {
        return self::format($this->amountMinor, $locale);
    }

    /**
     * @return array{amount_minor: int, currency: string}
     */
    public function toArray(): array
    {
        return ['amount_minor' => $this->amountMinor, 'currency' => self::CURRENCY];
    }

    /**
     * @return array{amount_minor: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * VAT of a net amount: intdiv(net × rate_bp + 5000, 10000), i.e. rounded half up.
     */
    public static function vat(int $netMinor, int $rateBp = self::DEFAULT_VAT_RATE_BP): int
    {
        if ($netMinor < 0 || $rateBp < 0) {
            throw new InvalidArgumentException('VAT is computed on non-negative amounts and rates only.');
        }

        return intdiv($netMinor * $rateBp + 5000, 10000);
    }

    /**
     * Rounds up (towards +∞) to a multiple of the granularity.
     */
    public static function ceilTo(int $value, int $granularity): int
    {
        if ($granularity <= 0) {
            throw new InvalidArgumentException('The granularity must be a positive integer.');
        }

        $remainder = $value % $granularity;

        if ($remainder === 0) {
            return $value;
        }

        return $value > 0 ? $value - $remainder + $granularity : $value - $remainder;
    }

    /**
     * The part as basis points of the whole: intdiv(part × 10000, whole), truncated toward zero.
     */
    public static function bps(int $part, int $whole): int
    {
        if ($whole === 0) {
            throw new InvalidArgumentException('The whole must not be zero.');
        }

        return intdiv($part * 10000, $whole);
    }

    /**
     * Display format of CONVENTIONS §9.2 (the same algorithm as the web and mobile formatMoney):
     * always 2 decimals, comma grouping, Western digits; "12,500.00 ر.س" in Arabic and
     * "SAR 12,500.00" otherwise.
     */
    public static function format(int $amountMinor, string $locale): string
    {
        $sign = $amountMinor < 0 ? '-' : '';
        $absolute = abs($amountMinor);
        $number = $sign.number_format(intdiv($absolute, 100), 0, '.', ',').'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $locale === 'ar' ? $number.' ر.س' : self::CURRENCY.' '.$number;
    }
}
