<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\AppV1;

use App\Modules\Bidding\Actions\IssueAward;
use App\Modules\Bidding\Actions\RevokeAward;
use App\Modules\Bidding\Http\Controllers\BiddingController;
use App\Modules\Bidding\Http\Requests\IssueAwardRequest;
use App\Modules\Bidding\Http\Requests\RevokeAwardRequest;
use App\Modules\Bidding\Http\Resources\AwardResource;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Competition;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

/**
 * Award (API.md §1.6, ARCHITECTURE §7.12).
 *
 *   POST /competitions/{competition}/award          issuer, `competitions.award` → 201 Award
 *   GET  /competitions/{competition}/award          issuer: Award; participant: its result
 *   POST /competitions/{competition}/award/revoke   issuer, `competitions.award` → Award (revoked)
 */
final class AwardController extends BiddingController
{
    public function store(IssueAwardRequest $request, Competition $competition, IssueAward $issue): JsonResponse
    {
        $this->issuerViewer($competition);
        $this->authorizeAbility($request, 'bidding.award');

        $award = $issue->handle(
            $competition,
            $request->participantId(),
            $request->justificationReason(),
            $request->justificationText(),
            $request->confirmReserveNotMet(),
            $request->messageToWinner(),
            $request->internalNotes(),
            $this->user($request),
            $this->actor(),
        );

        return $this->created(new AwardResource($award->load(AwardResource::relations())));
    }

    public function show(Competition $competition, VisibilityProjector $projector): JsonResponse
    {
        $viewer = $this->viewer($competition);

        if ($viewer->isIssuer()) {
            // CONTRACT-GAP: API.md says "null when there is no award". The issuer gets the issued
            // award, else the latest revoked one (the evaluation history), else null.
            $award = Award::query()
                ->where('competition_id', $competition->id)
                ->with(AwardResource::relations())
                ->orderByDesc('id')
                ->first();

            return $this->ok($award !== null ? new AwardResource($award) : null);
        }

        if ($viewer->isParticipant() && $viewer->participant !== null) {
            return $this->ok($projector->participantAward($competition, $viewer->participant));
        }

        throw new ApiException('not_a_participant', 'bidding.errors.not_a_participant', 403);
    }

    public function revoke(RevokeAwardRequest $request, Competition $competition, RevokeAward $revoke): JsonResponse
    {
        $this->issuerViewer($competition);
        $this->authorizeAbility($request, 'bidding.award');

        $award = $revoke->handle($competition, $request->reason(), $this->user($request), $this->actor());

        return $this->ok(new AwardResource($award->load(AwardResource::relations())));
    }
}
