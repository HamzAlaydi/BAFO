<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\OpenApi;

use JsonException;
use RuntimeException;
use stdClass;

/**
 * The public v1 OpenAPI 3.1 document (ARCHITECTURE §14.9).
 *
 * The hand-written source is `resources/openapi/public-v1.json`. `public-v1.yaml` next to it is
 * the same document rendered by YamlEmitter (`php artisan integrations:openapi` rewrites it; a
 * test keeps both in sync).
 *
 * CONTRACT-GAP: §14.9 names `public-v1.yaml` as the source. No YAML parser is installed
 * (`symfony/yaml` is not in the Platform-owned composer.json), so the JSON is the source and the
 * YAML is generated; both are served (`openapi.yaml`, `openapi.json`).
 */
final class OpenApiDocument
{
    public static function jsonPath(): string
    {
        return resource_path('openapi/public-v1.json');
    }

    public static function yamlPath(): string
    {
        return resource_path('openapi/public-v1.yaml');
    }

    public static function postmanPath(): string
    {
        return resource_path('openapi/bafo-public-v1.postman_collection.json');
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        $contents = @file_get_contents(self::jsonPath());

        if ($contents === false) {
            throw new RuntimeException('The OpenAPI document is missing: '.self::jsonPath());
        }

        try {
            $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The OpenAPI document is not valid JSON.', previous: $e);
        }

        if (! is_array($document)) {
            throw new RuntimeException('The OpenAPI document must be a JSON object.');
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    /**
     * The source bytes as written (re-encoding a decoded array would turn `{}` into `[]`).
     */
    public function json(): string
    {
        $this->document();

        return (string) file_get_contents(self::jsonPath());
    }

    public function yaml(): string
    {
        $contents = @file_get_contents(self::yamlPath());

        return $contents === false ? $this->renderYaml() : $contents;
    }

    public function renderYaml(): string
    {
        $document = json_decode((string) file_get_contents(self::jsonPath()), false, flags: JSON_THROW_ON_ERROR);

        if (! $document instanceof stdClass) {
            throw new RuntimeException('The OpenAPI document must be a JSON object.');
        }

        return YamlEmitter::emit($document);
    }
}
