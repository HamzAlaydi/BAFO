<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Console\Commands;

use App\Modules\Integrations\Services\OpenApi\OpenApiDocument;
use Illuminate\Console\Command;

/**
 * `integrations:openapi [--check]`: renders `resources/openapi/public-v1.yaml` from the JSON
 * source (ARCHITECTURE §14.9), or with `--check` fails when the committed YAML is stale.
 */
final class OpenApiCommand extends Command
{
    protected $signature = 'integrations:openapi {--check : Fail when public-v1.yaml differs from the rendered JSON}';

    protected $description = 'Render the public v1 OpenAPI YAML from its JSON source';

    public function handle(OpenApiDocument $document): int
    {
        $yaml = $document->renderYaml();

        if ($this->option('check')) {
            $current = @file_get_contents(OpenApiDocument::yamlPath());

            if ($current !== $yaml) {
                $this->components->error('public-v1.yaml is out of date: run php artisan integrations:openapi');

                return self::FAILURE;
            }

            $this->components->info('public-v1.yaml is up to date.');

            return self::SUCCESS;
        }

        file_put_contents(OpenApiDocument::yamlPath(), $yaml);
        $this->components->info('Wrote '.OpenApiDocument::yamlPath());

        return self::SUCCESS;
    }
}
