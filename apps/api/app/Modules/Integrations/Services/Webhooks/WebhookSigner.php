<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

/**
 * Standard Webhooks signing (ARCHITECTURE §14.5, API.md §4.3).
 *
 *   secret     `whsec_` + base64 of 32 random bytes; the HMAC key is the decoded part
 *   signature  `v1,` + base64(HMAC-SHA256(key, "{webhook-id}.{webhook-timestamp}.{body}"))
 */
final class WebhookSigner
{
    public const string SECRET_PREFIX = 'whsec_';

    public const int TOLERANCE_SECONDS = 300;

    public static function generateSecret(): string
    {
        return self::SECRET_PREFIX.base64_encode(random_bytes(32));
    }

    public static function sign(string $secret, string $webhookId, int $timestamp, string $body): string
    {
        return 'v1,'.base64_encode(hash_hmac('sha256', $webhookId.'.'.$timestamp.'.'.$body, self::key($secret), true));
    }

    /**
     * The consumer-side check of API.md §4.3: any `v1,` signature of the (space-separated)
     * header matches in constant time, and the timestamp is within 300 s of `$now`.
     */
    public static function verify(string $secret, string $webhookId, string $timestamp, string $signatureHeader, string $body, int $now): bool
    {
        if (! ctype_digit($timestamp) || abs($now - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = substr(self::sign($secret, $webhookId, (int) $timestamp, $body), 3);

        foreach (explode(' ', $signatureHeader) as $signature) {
            if (str_starts_with($signature, 'v1,') && hash_equals($expected, substr($signature, 3))) {
                return true;
            }
        }

        return false;
    }

    private static function key(string $secret): string
    {
        $encoded = str_starts_with($secret, self::SECRET_PREFIX) ? substr($secret, strlen(self::SECRET_PREFIX)) : $secret;

        return (string) base64_decode($encoded, true);
    }
}
