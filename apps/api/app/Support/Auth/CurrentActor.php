<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * The actor of the current request (ARCHITECTURE §4.1).
 *
 *   CurrentActor::get()   the actor set by ResolveActor (app v1) or `api.client` (public v1);
 *                         Actor::system() in jobs, console and anywhere nothing was set.
 *   CurrentActor::set()   called by those middleware (and by tests).
 *
 * The holder is a scoped container binding, so it is reset between requests and queued jobs.
 */
final class CurrentActor
{
    private ?Actor $actor = null;

    public static function get(): Actor
    {
        return self::instance()->actor ?? Actor::system();
    }

    public static function set(Actor $actor): void
    {
        self::instance()->actor = $actor;
    }

    public static function has(): bool
    {
        return self::instance()->actor !== null;
    }

    public static function clear(): void
    {
        self::instance()->actor = null;
    }

    private static function instance(): self
    {
        return app(self::class);
    }
}
