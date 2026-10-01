<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\AppV1;

use App\Modules\Platform\Services\AppConfigBuilder;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

/**
 * GET /api/app/v1/app-config (guest): the AppConfig resource of API.md §2.13. It is what a
 * client needs before sign-in: version gates, maintenance, support contacts, the realtime
 * connection, legal versions, feature flags and the server time. Not blocked by the
 * maintenance or version checks.
 */
final class AppConfigController extends ApiController
{
    public function __invoke(AppConfigBuilder $config): JsonResponse
    {
        return $this->ok($config->build(App::getLocale()))->withHeaders([
            'Cache-Control' => 'no-store',
            'Vary' => 'Accept-Language',
        ]);
    }
}
