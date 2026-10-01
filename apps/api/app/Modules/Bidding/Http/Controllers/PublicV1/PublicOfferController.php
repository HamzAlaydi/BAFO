<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\PublicV1;

use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

/**
 * Offers and results for the issuer's ERP (API.md §3.5, flows F4 and F5), issuer projection.
 *
 *   GET /competitions/{competition}/offers    offers:read   [PublicStandingRow]
 *   GET /competitions/{competition}/results   offers:read   PublicResults (409 before close)
 */
final class PublicOfferController extends PublicController
{
    private const array RESULT_STATUSES = [
        CompetitionStatus::Closed,
        CompetitionStatus::BafoRound,
        CompetitionStatus::Awarded,
        CompetitionStatus::NotAwarded,
        CompetitionStatus::Cancelled,
    ];

    public function index(string $competition, VisibilityProjector $projector): JsonResponse
    {
        return $this->ok($projector->standingRows($this->competition($competition), withVendor: true));
    }

    public function results(string $competition, VisibilityProjector $projector): JsonResponse
    {
        $model = $this->competition($competition);

        if (! in_array($model->status, self::RESULT_STATUSES, true)) {
            throw new ApiException('results_not_available', 'bidding.errors.results_not_available', 409);
        }

        return $this->ok($projector->publicResults($model));
    }
}
