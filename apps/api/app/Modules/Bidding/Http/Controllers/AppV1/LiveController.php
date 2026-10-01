<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\AppV1;

use App\Modules\Bidding\Http\Controllers\BiddingController;
use App\Modules\Bidding\Services\Heartbeats;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Competition;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The live view (API.md §1.6, ARCHITECTURE §9.4, §9.5).
 *
 *   GET  /competitions/{competition}/live             issuer / participant snapshot, `no-store`
 *   POST /competitions/{competition}/live/heartbeat   participant presence, 204
 */
final class LiveController extends BiddingController
{
    public function show(Competition $competition, VisibilityProjector $projector): JsonResponse
    {
        $viewer = $this->viewer($competition);

        // CONTRACT-GAP: "available from publish onward"; a draft has no live view (404).
        if ($competition->isDraft()) {
            throw new ApiException('not_found', status: 404);
        }

        if ($viewer->isIssuer()) {
            $snapshot = $projector->issuerSnapshot($competition);
        } elseif ($viewer->isParticipant() && $viewer->participant !== null) {
            $snapshot = $projector->participantSnapshot($competition, $viewer->participant);
        } else {
            throw new ApiException('not_a_participant', 'bidding.errors.not_a_participant', 403);
        }

        return $this->ok($snapshot)->header('Cache-Control', 'no-store, private');
    }

    /**
     * CONTRACT-GAP: §9.5 does not limit the heartbeat to a status; it is accepted whenever the
     * caller is a participant (the key simply expires).
     */
    public function heartbeat(Request $request, Competition $competition, Heartbeats $heartbeats): JsonResponse
    {
        $this->authorizeAbility($request, 'bidding.submit-offers');
        $viewer = $this->participantViewer($competition);

        $heartbeats->beat($competition->id, (int) $viewer->participant?->id, $this->user($request)->id);

        return $this->noContent();
    }
}
