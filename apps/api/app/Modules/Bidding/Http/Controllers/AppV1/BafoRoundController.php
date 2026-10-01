<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\AppV1;

use App\Modules\Bidding\Actions\StartBafoRound;
use App\Modules\Bidding\Http\Controllers\BiddingController;
use App\Modules\Bidding\Http\Requests\StartBafoRoundRequest;
use App\Modules\Bidding\Services\CompetitionPayload;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Http\JsonResponse;

/**
 * POST /competitions/{competition}/bafo-round (API.md §1.6, ARCHITECTURE §7.11): issuer with
 * `competitions.award`. Returns the competition (status `bafo_round`).
 */
final class BafoRoundController extends BiddingController
{
    public function store(StartBafoRoundRequest $request, Competition $competition, StartBafoRound $start, CompetitionPayload $payload): JsonResponse
    {
        $viewer = $this->issuerViewer($competition);
        $this->authorizeAbility($request, 'bidding.award');

        $start->handle($competition, $request->participantIds(), $request->durationMinutes(), $this->user($request), $this->actor());

        return $this->ok($payload->for(Competition::query()->findOrFail($competition->id), $viewer, $this->user($request)));
    }
}
