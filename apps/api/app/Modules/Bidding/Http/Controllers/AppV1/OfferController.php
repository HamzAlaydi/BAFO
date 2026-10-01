<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\AppV1;

use App\Modules\Bidding\Actions\SubmitOffer;
use App\Modules\Bidding\Data\LastChange;
use App\Modules\Bidding\Enums\LiveChangeKind;
use App\Modules\Bidding\Http\Controllers\BiddingController;
use App\Modules\Bidding\Http\Requests\OfferLogRequest;
use App\Modules\Bidding\Http\Requests\SubmitOfferRequest;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Competition;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use App\Support\Http\Middleware\IdempotentRequest;
use Illuminate\Http\JsonResponse;

/**
 * Offers (API.md §1.6).
 *
 *   POST /competitions/{competition}/offers        participant, `throttle:offers`, Idempotency-Key
 *   GET  /competitions/{competition}/offers        issuer: [ParticipantStandingRow]
 *   GET  /competitions/{competition}/offers/log    issuer: [OfferLogEntry] by seq
 *   GET  /competitions/{competition}/my-offers     participant: [MyOffer]
 */
final class OfferController extends BiddingController
{
    private const string KEY_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    /**
     * ARCHITECTURE §7.4. The route parameter is resolved here, not by route-model binding, so
     * that the pre-checks run in the contract's order: 1 permission, 2 Idempotency-Key,
     * 3 visibility (404), 4 participant (403 `not_a_participant`).
     */
    public function store(SubmitOfferRequest $request, string $competition, SubmitOffer $submit, VisibilityProjector $projector): JsonResponse
    {
        $user = $this->user($request);

        // 1. Permission.
        $this->authorizeAbility($request, 'bidding.submit-offers');

        // 2. Idempotency-Key.
        $key = trim((string) $request->header(IdempotentRequest::HEADER, ''));

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new ApiException('idempotency_key_required', status: 400);
        }

        // 3. Visibility; 4. participant.
        $model = Competition::findByPublicId($competition) ?? throw new ApiException('not_found', status: 404);
        $participant = $this->participantViewer($model)->participant;

        if ($participant === null) {
            throw new ApiException('not_a_participant', 'bidding.errors.not_a_participant', 403);
        }

        $result = $submit->handle($model, $participant, $user, $request->amount(), $request->confirmOutlier(), $key, $this->actor());

        // Built after commit: the snapshot may already include later offers; its `v` says so.
        $fresh = Competition::query()->findOrFail($model->id);
        $change = $result->replayed ? LastChange::snapshot() : LastChange::of(LiveChangeKind::Offer);
        $data = [
            'offer' => [
                ...$projector->offerSummary($result->offer),
                'voided' => $result->replayed && $result->offer->void()->exists(),
            ],
            'live' => $projector->participantSnapshot($fresh, $participant, $change),
        ];

        return $result->replayed
            ? ApiResponse::ok($data, [], 200, [IdempotentRequest::REPLAYED_HEADER => 'true'])
            : $this->created($data);
    }

    public function index(Competition $competition, VisibilityProjector $projector): JsonResponse
    {
        $this->issuerViewer($competition);

        return $this->ok($projector->standingRows($competition));
    }

    public function log(OfferLogRequest $request, Competition $competition, VisibilityProjector $projector): JsonResponse
    {
        $this->issuerViewer($competition);

        $afterSeq = $request->afterSeq();
        $limit = $request->limit();

        $offers = Offer::query()
            ->where('competition_id', $competition->id)
            ->where('seq', '>', $afterSeq)
            ->with('participant.organization')
            ->withExists('void')
            ->orderBy('seq')
            ->limit($limit + 1)
            ->get();

        $hasMore = $offers->count() > $limit;
        $page = $offers->take($limit)->values();

        return $this->ok(
            $page->map(static fn (Offer $offer): array => $projector->offerLogEntry($offer, $competition))->all(),
            ['last_seq' => $page->last()->seq ?? $afterSeq, 'has_more' => $hasMore],
        );
    }

    public function mine(Competition $competition, VisibilityProjector $projector): JsonResponse
    {
        $participant = $this->participantViewer($competition)->participant;

        return $this->ok($participant !== null ? $projector->myOffers($participant) : []);
    }
}
