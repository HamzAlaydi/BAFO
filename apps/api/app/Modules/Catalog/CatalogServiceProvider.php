<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Catalog module.
 *
 * Reference data: regions, categories (incl. auction_allowed), close reasons and
 * competition presets, with their lookup endpoints (app and public) and reference seeders.
 *
 * HTTP routes: routes/app_v1/catalog.php and routes/public_v1/catalog.php.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class CatalogServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [];
}
