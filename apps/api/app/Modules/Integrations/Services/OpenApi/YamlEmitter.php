<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\OpenApi;

use stdClass;

/**
 * A minimal YAML 1.2 emitter for decoded JSON (`json_decode($json)` without `assoc`: objects are
 * stdClass, lists are arrays, so `{}` and `[]` stay distinct). Used to publish the OpenAPI
 * document as YAML. Strings are double-quoted JSON strings unless they are plain-safe, so the
 * output parses back to the same data.
 */
final class YamlEmitter
{
    public static function emit(stdClass $document): string
    {
        return self::block($document, 0)."\n";
    }

    /**
     * @param  stdClass|list<mixed>  $node
     */
    private static function block(stdClass|array $node, int $indent): string
    {
        $pad = str_repeat('  ', $indent);
        $lines = [];

        if (is_array($node)) {
            foreach ($node as $item) {
                $lines[] = self::isNonEmptyCollection($item)
                    ? $pad.'- '.ltrim(self::block($item, $indent + 1))
                    : $pad.'- '.self::scalar($item);
            }

            return implode("\n", $lines);
        }

        foreach (get_object_vars($node) as $key => $value) {
            $key = self::key((string) $key);

            if (self::isNonEmptyCollection($value)) {
                $lines[] = $pad.$key.':';
                $lines[] = self::block($value, $indent + 1);
            } else {
                $lines[] = $pad.$key.': '.self::scalar($value);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @phpstan-assert-if-true stdClass|list<mixed> $value
     */
    private static function isNonEmptyCollection(mixed $value): bool
    {
        return (is_array($value) && $value !== []) || ($value instanceof stdClass && get_object_vars($value) !== []);
    }

    private static function key(string $key): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $key) === 1 ? $key : self::quote($key);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_int($value), is_float($value) => json_encode($value, JSON_THROW_ON_ERROR),
            is_array($value) => '[]',
            $value instanceof stdClass => '{}',
            is_string($value) => self::plainSafe($value) ? $value : self::quote($value),
            default => self::quote(''),
        };
    }

    private static function plainSafe(string $value): bool
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9 _.,\/()\-]*$/', $value) !== 1 || str_ends_with($value, ' ')) {
            return false;
        }

        // Words YAML 1.1 readers take as booleans or null stay quoted.
        return ! in_array(strtolower($value), ['true', 'false', 'yes', 'no', 'on', 'off', 'null', 'y', 'n'], true);
    }

    private static function quote(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
