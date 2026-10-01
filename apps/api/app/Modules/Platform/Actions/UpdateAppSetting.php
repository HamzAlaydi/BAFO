<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Admin settings page: changes one runtime setting of the §15.3 catalogue (a key some module
 * registered a default for).
 */
final readonly class UpdateAppSetting
{
    public function __construct(private Settings $settings) {}

    public function handle(string $key, mixed $value, Actor $actor): void
    {
        if (! array_key_exists($key, $this->settings->registeredDefaults())) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        DB::transaction(function () use ($key, $value, $actor): void {
            $from = $this->settings->get($key);
            $this->settings->set($key, $value, $actor->adminId);

            AuditLogger::log('setting.updated', null, [$key => ['from' => $from, 'to' => $value]], ['key' => $key], $actor);
        });
    }
}
