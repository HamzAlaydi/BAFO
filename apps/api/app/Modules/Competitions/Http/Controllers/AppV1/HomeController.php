<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Queries\HomeStats;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * `GET /home` (API.md §1.4, §2.13): the dashboard home of the caller's organization.
 */
final class HomeController extends ApiController
{
    use ActsForCaller;

    public function __invoke(HomeStats $stats): JsonResponse
    {
        return $this->ok($stats->for($this->organization()));
    }
}
