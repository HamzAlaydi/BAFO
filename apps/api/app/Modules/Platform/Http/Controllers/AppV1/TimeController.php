<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\AppV1;

use App\Support\Http\Controllers\ApiController;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

/**
 * GET /api/app/v1/time: the authoritative server clock, for client countdown sync.
 */
final class TimeController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->ok(['server_time' => Iso::format(Date::now())])
            ->header('Cache-Control', 'no-store');
    }
}
