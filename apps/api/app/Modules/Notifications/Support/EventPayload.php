<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use UnexpectedValueException;

/**
 * Typed reads of the public properties of another module's domain event (ARCHITECTURE §10).
 *
 * The Notifications listeners are bound to events by class-string (§3.4), so they work in
 * whatever order the producing modules land. They read the §10 property names through this
 * class, which checks each type at runtime: an event that drifts from the contract fails the
 * queued job loudly instead of sending a wrong notification.
 */
final class EventPayload
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function instance(object $event, string $property, string $class): object
    {
        $value = self::read($event, $property);

        if (! $value instanceof $class) {
            throw self::mismatch($event, $property, $class);
        }

        return $value;
    }

    /**
     * The first public property holding an instance of the class, for events whose §10 row
     * gives the type but not the property name.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function find(object $event, string $class): object
    {
        foreach (get_object_vars($event) as $value) {
            if ($value instanceof $class) {
                return $value;
            }
        }

        throw new UnexpectedValueException(sprintf('Event %s carries no %s (ARCHITECTURE §10).', $event::class, $class));
    }

    public static function object(object $event, string $property): object
    {
        $value = self::read($event, $property);

        return is_object($value) ? $value : throw self::mismatch($event, $property, 'object');
    }

    public static function int(object $event, string $property): int
    {
        $value = self::read($event, $property);

        return is_int($value) ? $value : throw self::mismatch($event, $property, 'int');
    }

    public static function nullableInt(object $event, string $property): ?int
    {
        $value = self::read($event, $property);

        return $value === null || is_int($value) ? $value : throw self::mismatch($event, $property, '?int');
    }

    public static function bool(object $event, string $property): bool
    {
        $value = self::read($event, $property);

        return is_bool($value) ? $value : throw self::mismatch($event, $property, 'bool');
    }

    public static function string(object $event, string $property): string
    {
        $value = self::read($event, $property);

        return is_string($value) ? $value : throw self::mismatch($event, $property, 'string');
    }

    /**
     * @return list<string>
     */
    public static function strings(object $event, string $property): array
    {
        $value = self::read($event, $property);

        if (! is_array($value)) {
            throw self::mismatch($event, $property, 'list<string>');
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private static function read(object $event, string $property): mixed
    {
        if (! property_exists($event, $property)) {
            throw new UnexpectedValueException(sprintf('Event %s has no property $%s (ARCHITECTURE §10).', $event::class, $property));
        }

        return $event->{$property};
    }

    private static function mismatch(object $event, string $property, string $expected): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('Event %s::$%s must be %s (ARCHITECTURE §10).', $event::class, $property, $expected));
    }
}
