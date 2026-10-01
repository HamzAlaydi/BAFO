<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

use App\Modules\Notifications\Enums\DevicePlatform;

/**
 * The validated input of `POST /devices` (API.md §1.8).
 */
final readonly class DeviceRegistration
{
    public function __construct(
        public string $token,
        public DevicePlatform $platform,
        public ?string $deviceName,
        public ?string $appVersion,
        public string $locale,
    ) {}
}
