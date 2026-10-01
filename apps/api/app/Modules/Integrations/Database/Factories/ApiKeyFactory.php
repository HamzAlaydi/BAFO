<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A valid test-environment key (+365 days). Use `withPlainKey()` when the test needs the plain
 * key; otherwise the plain key is random and lost, as in production.
 *
 * @extends Factory<ApiKey>
 */
final class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * `bafo_{env}_{prefix8}_{secret32}` (ARCHITECTURE §14.1).
     */
    public static function makePlainKey(string $environment = 'test'): string
    {
        return 'bafo_'.$environment.'_'.Str::lower(Str::random(8)).'_'.Str::random(32);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_client_id' => ApiClient::factory(),
            ...self::columnsFor(self::makePlainKey()),
            'expires_at' => now()->addDays(365),
            'revoked_at' => null,
            'created_by_user_id' => null,
        ];
    }

    public function withPlainKey(string $plainKey): self
    {
        return $this->state(self::columnsFor($plainKey));
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }

    /**
     * @return array{prefix: string, key_hash: string, last_four: string}
     */
    private static function columnsFor(string $plainKey): array
    {
        if (preg_match('/^bafo_(live|test)_([a-z0-9]{8})_[A-Za-z0-9]{32}$/', $plainKey, $matches) !== 1) {
            throw new InvalidArgumentException('Not a BAFO API key: bafo_{live|test}_{prefix8}_{secret32}.');
        }

        return [
            'prefix' => $matches[2],
            'key_hash' => hash('sha256', $plainKey),
            'last_four' => substr($plainKey, -4),
        ];
    }
}
