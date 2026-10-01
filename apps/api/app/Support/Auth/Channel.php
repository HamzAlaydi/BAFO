<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * The surface an action came through (ARCHITECTURE §4.1). Stored in audit_logs.channel and
 * used as the offer channel.
 */
enum Channel: string
{
    case Web = 'web';
    case Ios = 'ios';
    case Android = 'android';
    case Api = 'api';
    case Admin = 'admin';
    case System = 'system';

    /**
     * App v1 channel from the X-Platform header: ios → ios, android → android, anything else → web.
     */
    public static function fromPlatformHeader(?string $platform): self
    {
        return match (strtolower(trim((string) $platform))) {
            'ios' => self::Ios,
            'android' => self::Android,
            default => self::Web,
        };
    }

    public function isMobile(): bool
    {
        return $this === self::Ios || $this === self::Android;
    }
}
