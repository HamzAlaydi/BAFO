<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use InvalidArgumentException;

/**
 * Reads a typed public property of a domain event.
 *
 * The Integrations listeners are registered by class-string name (ARCHITECTURE §3.4), so they
 * work before the producing module's event class exists and never import it: they receive the
 * event as `object` and read the §10 properties by name.
 */
final class EventProperty
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function get(object $event, string $property, string $class): object
    {
        $value = self::value($event, $property);

        if (! $value instanceof $class) {
            throw new InvalidArgumentException(sprintf('Event [%s] has no [%s] property of type [%s].', $event::class, $property, $class));
        }

        return $value;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public static function optional(object $event, string $property, string $class): ?object
    {
        $value = self::value($event, $property);

        return $value instanceof $class ? $value : null;
    }

    public static function bool(object $event, string $property): ?bool
    {
        $value = self::value($event, $property);

        return is_bool($value) ? $value : null;
    }

    public static function int(object $event, string $property): ?int
    {
        $value = self::value($event, $property);

        return is_int($value) ? $value : null;
    }

    public static function value(object $event, string $property): mixed
    {
        return get_object_vars($event)[$property] ?? null;
    }
}
