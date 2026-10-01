<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\AppV1;

use App\Modules\Platform\Services\SystemHealth;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/app/v1/health (guest): database, Redis and queue checks. 200 when every check
 * passes, 503 with the same body shape when one is down, so load balancers can use it.
 */
final class HealthController extends ApiController
{
    public function __invoke(SystemHealth $health): JsonResponse
    {
        $report = $health->report();

        return $this->ok($report, status: $report['status'] === SystemHealth::OK ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
