<?php

declare(strict_types=1);

namespace Tests\Support\Integrations;

use Laravel\Passport\Passport;
use RuntimeException;

/**
 * Test RSA keys for Passport (ARCHITECTURE §14.2): generated once per test process in a temporary
 * directory (mode 0600, as league/oauth2-server checks) and loaded with Passport::loadKeysFrom().
 */
final class PassportKeys
{
    private static ?string $directory = null;

    public static function load(): void
    {
        Passport::loadKeysFrom(self::directory());
    }

    private static function directory(): string
    {
        if (self::$directory !== null) {
            return self::$directory;
        }

        $directory = sys_get_temp_dir().'/bafo-passport-test-keys-'.getmypid();

        if (! is_file($directory.'/oauth-private.key') || ! is_file($directory.'/oauth-public.key')) {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create {$directory}.");
            }

            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

            if ($key === false || ! openssl_pkey_export($key, $private)) {
                throw new RuntimeException('Cannot generate the Passport test keys.');
            }

            $details = openssl_pkey_get_details($key);

            file_put_contents($directory.'/oauth-private.key', $private);
            file_put_contents($directory.'/oauth-public.key', is_array($details) ? $details['key'] : '');
            chmod($directory.'/oauth-private.key', 0600);
            chmod($directory.'/oauth-public.key', 0600);
        }

        return self::$directory = $directory;
    }
}
