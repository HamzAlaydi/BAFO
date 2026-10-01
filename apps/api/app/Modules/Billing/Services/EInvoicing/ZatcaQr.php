<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services\EInvoicing;

use InvalidArgumentException;

/**
 * The ZATCA simplified-invoice QR payload: base64 of TLV records (one byte tag, one byte
 * length in UTF-8 bytes, the value). ARCHITECTURE §13.7 uses tags 1–5:
 *
 *   1 seller name (Arabic) · 2 seller VAT number · 3 issue timestamp (ISO 8601)
 *   4 total incl. VAT (decimal string) · 5 VAT total (decimal string)
 */
final class ZatcaQr
{
    /**
     * @param  array<int, string>  $fields  tag → value
     */
    public static function encode(array $fields): string
    {
        ksort($fields);
        $binary = '';

        foreach ($fields as $tag => $value) {
            $length = strlen($value);

            if ($tag < 1 || $tag > 255 || $length > 255) {
                throw new InvalidArgumentException("TLV field {$tag} is out of range.");
            }

            $binary .= chr($tag).chr($length).$value;
        }

        return base64_encode($binary);
    }

    /**
     * @return array<int, string> tag → value
     */
    public static function decode(string $payload): array
    {
        $binary = base64_decode($payload, true);

        if ($binary === false) {
            throw new InvalidArgumentException('The QR payload is not base64.');
        }

        $fields = [];
        $offset = 0;
        $size = strlen($binary);

        while ($offset + 2 <= $size) {
            $tag = ord($binary[$offset]);
            $length = ord($binary[$offset + 1]);
            $fields[$tag] = substr($binary, $offset + 2, $length);
            $offset += 2 + $length;
        }

        return $fields;
    }

    /**
     * Halalas as a decimal string with two places: 172500 → "1725.00".
     */
    public static function amount(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
