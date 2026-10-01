<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\ApiKey;
use Illuminate\Support\Str;

/**
 * Plain API keys (ARCHITECTURE §14.1): `bafo_{env}_{prefix8}_{secret32}`, where env is
 * `bafo.integrations.key_environment`, prefix8 is 8 × [a-z0-9] (unique) and secret32 is
 * 32 × [A-Za-z0-9]. Only the prefix, the sha256 of the full key and its last four characters
 * are stored.
 */
final class ApiKeyGenerator
{
    private const string PREFIX_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function generate(): string
    {
        do {
            $prefix = self::prefix();
        } while (ApiKey::query()->where('prefix', $prefix)->exists());

        return 'bafo_'.self::environment().'_'.$prefix.'_'.Str::random(32);
    }

    public static function environment(): string
    {
        $environment = config('bafo.integrations.key_environment');

        return $environment === 'live' ? 'live' : 'test';
    }

    /**
     * The display form of a stored key: `bafo_test_ab12cd34_…WXYZ` (API.md §2.12).
     */
    public static function mask(ApiKey $key): string
    {
        return 'bafo_'.self::environment().'_'.$key->prefix.'_…'.$key->last_four;
    }

    private static function prefix(): string
    {
        $prefix = '';

        for ($i = 0; $i < 8; $i++) {
            $prefix .= self::PREFIX_ALPHABET[random_int(0, strlen(self::PREFIX_ALPHABET) - 1)];
        }

        return $prefix;
    }
}
