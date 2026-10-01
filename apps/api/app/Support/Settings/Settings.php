<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;

/**
 * Admin-editable runtime settings (ARCHITECTURE §4.7; key catalogue §15.3).
 *
 *   defaults(['competitions.max_participants' => 200])   each owning module, in boot()
 *   get('competitions.max_participants')                 stored value, else the registered
 *                                                        default, else $default
 *   set('app.maintenance.enabled', true, $adminId)       admin panel (through an Action)
 *
 * Values are JSON (scalars, lists, objects) in `app_settings.value`. The whole table is
 * cached for 60 s under `settings:all` and memoised per request/job; set() clears it.
 */
final class Settings
{
    public const string CACHE_KEY = 'settings:all';

    public const int CACHE_SECONDS = 60;

    /**
     * @var array<string, mixed>
     */
    private array $defaults = [];

    public function __construct(private readonly CacheManager $cache) {}

    /**
     * Registers default values. Later registrations of the same key win.
     *
     * @param  array<string, mixed>  $map
     */
    public function defaults(array $map): void
    {
        $this->defaults = [...$this->defaults, ...$map];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return array_key_exists($key, $this->defaults) ? $this->defaults[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->stored()) || array_key_exists($key, $this->defaults);
    }

    public function set(string $key, mixed $value, ?int $adminId): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by_admin_id' => $adminId],
        );

        $this->flush();
    }

    /**
     * Removes a stored value, so the registered default applies again.
     */
    public function forget(string $key): void
    {
        AppSetting::query()->where('key', $key)->delete();

        $this->flush();
    }

    /**
     * Every known setting: the registered defaults overlaid with the stored values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [...$this->defaults, ...$this->stored()];
    }

    /**
     * @return array<string, mixed>
     */
    public function registeredDefaults(): array
    {
        return $this->defaults;
    }

    public function flush(): void
    {
        $this->store()->forget(self::CACHE_KEY);
        $this->cache->store()->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = $this->store()->remember(
            self::CACHE_KEY,
            self::CACHE_SECONDS,
            static fn (): array => AppSetting::query()->pluck('value', 'key')->all(),
        );

        return $stored;
    }

    private function store(): Repository
    {
        return $this->cache->memo();
    }
}
