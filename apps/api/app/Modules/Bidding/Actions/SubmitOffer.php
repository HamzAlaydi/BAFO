<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Actions;

use App\Modules\Bidding\Data\OfferAcceptedContext;
use App\Modules\Bidding\Data\SubmittedOffer;
use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Bidding\Events\OfferAccepted;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Bidding\Services\OfferRules;
use App\Modules\Bidding\Services\Ranking;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Contracts\CompetitionTimingService;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Auth\Channel;
use App\Support\Clock\DbClock;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Iso;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Offer acceptance, `POST /competitions/{id}/offers` (ARCHITECTURE §7.4). The controller runs
 * pre-checks 1–4 (permission, Idempotency-Key, visibility, participant); this Action runs steps
 * 5–21 in the contract's exact order:
 *
 *   5  replay (ledger row, then cached rejection)      6  per-participant rate limit
 *   7  syntax (positive integer, maximum, granularity)
 *   TX 8 lock the competition row · 9 DB clock after the lock · 10 state and stage
 *      11 live state + standing · 12 direction · 13 start price · 14 stage bound (step,
 *      must-beat, BAFO reference) · 15 outlier guard · 16 ledger insert with hash chain ·
 *      17 standing · 18 ranking · 19 anti-sniping · 20 live state · 21 OfferAccepted
 *
 * A business rejection in steps 10–15 rolls back, is written to `offer_rejections` outside the
 * transaction, cached per (participant, key) for 24 h, and re-thrown.
 *
 * Offer acceptance writes no audit entry: the ledger is its own audit (§4.5).
 */
final readonly class SubmitOffer
{
    public function __construct(
        private DbClock $clock,
        private Settings $settings,
        private Ranking $ranking,
        private LiveStateManager $liveStates,
        private VisibilityProjector $projector,
        private CompetitionTimingService $timing,
    ) {}

    public function handle(
        Competition $competition,
        Participant $participant,
        User $user,
        mixed $amount,
        bool $confirmOutlier,
        string $idempotencyKey,
        Actor $actor,
    ): SubmittedOffer {
        $receivedAt = CarbonImmutable::instance(Date::now());

        // 5. Replay: the ledger row, then a cached rejection.
        $existing = $this->findByKey($participant, $idempotencyKey);

        if ($existing !== null) {
            return $this->replay($existing, $amount);
        }

        $this->replayRejection($participant, $idempotencyKey, $amount);

        // 6. Rate limit per participant.
        $interval = max(1, $this->intSetting('bidding.offer_min_interval_seconds', 2));

        if (! Cache::add("offer-rate:{$participant->id}", 1, $interval)) {
            throw new ApiException(
                errorCode: 'too_many_requests',
                status: 429,
                headers: ['Retry-After' => (string) $interval],
                details: ['retry_after_seconds' => $interval],
            );
        }

        // 7. Syntax.
        $amountMinor = $this->parseAmount($amount);
        $maxAmount = $this->intSetting('bidding.max_amount_minor', 1_000_000_000_000);

        if ($amountMinor > $maxAmount) {
            throw new ApiException('offer_amount_too_large', 'bidding.errors.offer_amount_too_large', 422, details: ['max_amount_minor' => $maxAmount]);
        }

        $granularity = max(1, $competition->amount_granularity_minor);

        if ($amountMinor % $granularity !== 0) {
            throw new ApiException('offer_granularity', 'bidding.errors.offer_granularity', 422, details: ['granularity_minor' => $granularity]);
        }

        /** @var array{db_time: CarbonImmutable|null, stage: OfferStage|null, rejection: ApiException|null} $trace */
        $trace = ['db_time' => null, 'stage' => null, 'rejection' => null];

        try {
            return DB::transaction(
                function () use ($competition, $participant, $user, $amountMinor, $confirmOutlier, $idempotencyKey, $actor, &$trace): SubmittedOffer {
                    return $this->accept($competition, $participant, $user, $amountMinor, $confirmOutlier, $idempotencyKey, $actor, $trace);
                },
                1,
            );
        } catch (ApiException $exception) {
            if ($exception === $trace['rejection']) {
                $this->recordRejection($competition, $participant, $user, $amountMinor, $idempotencyKey, $actor, $receivedAt, $trace['db_time'], $trace['stage'], $exception);
            }

            throw $exception;
        }
    }

    /**
     * Steps 8–21, inside the transaction.
     *
     * @param  array{db_time: CarbonImmutable|null, stage: OfferStage|null, rejection: ApiException|null}  $trace
     */
    private function accept(
        Competition $competition,
        Participant $participant,
        User $user,
        int $amount,
        bool $confirmOutlier,
        string $idempotencyKey,
        Actor $actor,
        array &$trace,
    ): SubmittedOffer {
        // 8. Serialisation point: the competition row.
        $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

        // 9. The DB clock, read after the lock.
        $now = $this->clock->now();
        $trace['db_time'] = $now;

        // A concurrent request with the same key committed while this one waited for the lock.
        $duplicate = $this->findByKey($participant, $idempotencyKey);

        if ($duplicate !== null) {
            return $this->replay($duplicate, $amount);
        }

        $standing = ParticipantStanding::query()->find($participant->id);

        try {
            // 10. State and stage.
            $stage = $this->stage($locked, $standing, $now);
            $trace['stage'] = $stage;

            // 11. Live state and standing (created lazily under the lock).
            $state = $this->liveStates->forLocked($locked);
            $standing ??= ParticipantStanding::query()->create([
                'participant_id' => $participant->id,
                'competition_id' => $locked->id,
            ]);

            // 12–15. Amount rules.
            $this->checkAmount($locked, $state->leader_amount_minor, $standing, $stage, $amount, $confirmOutlier);
        } catch (ApiException $exception) {
            $trace['rejection'] = $exception;

            throw $exception;
        }

        // 16. The ledger row.
        $isFirst = ! Offer::query()->where('participant_id', $participant->id)->exists();
        $seq = $state->last_seq + 1;
        $rankKey = OfferRules::rankKey($locked, $amount);
        $hash = Offer::hashFor($state->ledger_head_hash, $locked->public_id, $seq, $participant->public_id, $amount, $stage, $now);

        $offer = Offer::query()->create([
            'competition_id' => $locked->id,
            'participant_id' => $participant->id,
            'organization_id' => $participant->organization_id,
            'submitted_by_user_id' => $user->id,
            'seq' => $seq,
            'stage' => $stage,
            'amount_minor' => $amount,
            'rank_key' => $rankKey,
            'accepted_at' => $now,
            'idempotency_key' => $idempotencyKey,
            'channel' => self::offerChannel($actor),
            'ip' => $actor->ip,
            'user_agent' => $actor->userAgent,
            'outlier_confirmed' => $confirmOutlier,
            'prev_hash' => $state->ledger_head_hash,
            'hash' => $hash,
            'created_at' => $now,
        ]);

        // 17. The standing.
        $standing->fill([
            'current_offer_id' => $offer->id,
            'current_amount_minor' => $amount,
            'current_rank_key' => $rankKey,
            'current_at' => $now,
            'current_seq' => $seq,
            'first_amount_minor' => $standing->first_amount_minor ?? $amount,
            'offers_count' => $standing->offers_count + 1,
            'last_offer_at' => $now,
        ]);

        if ($stage === OfferStage::Bafo) {
            $standing->bafo_offer_id = $offer->id;
        }

        $standing->save();

        // 18. Ranking.
        $ranking = $this->ranking->recompute($locked, $now);

        // 19. Anti-sniping (stage live only).
        $extended = $stage === OfferStage::Live && $this->antiSnipe($locked, $now, $ranking->leaderChanged, $offer);

        // 20. The live state.
        $previousLeaderAmount = $state->leader_amount_minor;
        $state->version++;
        $state->last_seq = $seq;
        $state->ledger_head_hash = $hash;
        $state->accepted_offer_count++;
        $this->liveStates->syncFromStandings($locked, $state);
        $state->save();

        // 21. The domain event (the sync webhook-outbox listener runs here).
        event(new OfferAccepted($offer, $locked, new OfferAcceptedContext(
            isFirstOfferOfParticipant: $isFirst,
            leaderChanged: $ranking->leaderChanged,
            previousLeaderParticipantId: $ranking->previousLeaderParticipantId,
            changedParticipantIds: array_values(array_unique([$participant->id, ...$ranking->changedParticipantIds])),
            extended: $extended,
            version: $state->version,
            leadingAmountChanged: $previousLeaderAmount !== $state->leader_amount_minor,
        )));

        return new SubmittedOffer($offer, replayed: false);
    }

    /**
     * Step 10: whether the competition accepts an offer from this participant now, and in
     * which stage (§7.3).
     */
    private function stage(Competition $locked, ?ParticipantStanding $standing, CarbonImmutable $now): OfferStage
    {
        if ($locked->status === CompetitionStatus::Live) {
            if ($locked->bidding_opens_at !== null && $now->lessThan($locked->bidding_opens_at)) {
                throw new ApiException('offer_not_accepting', 'bidding.errors.offer_not_accepting', 409, details: [
                    'status' => $locked->status->value,
                    'opens_at' => Iso::format($locked->bidding_opens_at),
                ]);
            }

            if ($locked->effective_close_at === null || $now->greaterThanOrEqualTo($locked->effective_close_at)) {
                throw new ApiException('offer_closed', 'bidding.errors.offer_closed', 409, details: [
                    'closed_at' => Iso::format($locked->effective_close_at),
                ]);
            }

            return $this->projector->stageAt($locked, $now)
                ?? throw new ApiException('offer_not_accepting', 'bidding.errors.offer_not_accepting', 409, details: ['status' => $locked->status->value]);
        }

        if ($locked->status === CompetitionStatus::BafoRound) {
            $round = BafoRound::query()->where('competition_id', $locked->id)->first();

            if ($standing === null || ! $standing->bafo_shortlisted) {
                throw new ApiException('offer_not_shortlisted', 'bidding.errors.offer_not_shortlisted', 403);
            }

            if ($round === null || $now->greaterThanOrEqualTo($round->cutoff_at)) {
                throw new ApiException('offer_closed', 'bidding.errors.offer_closed', 409, details: [
                    'closed_at' => Iso::format($round?->cutoff_at),
                ]);
            }

            if ($standing->bafo_offer_id !== null) {
                throw new ApiException('offer_bafo_already_submitted', 'bidding.errors.offer_bafo_already_submitted', 409);
            }

            return OfferStage::Bafo;
        }

        throw new ApiException('offer_not_accepting', 'bidding.errors.offer_not_accepting', 409, details: [
            'status' => $locked->status->value,
        ]);
    }

    /**
     * Steps 12–15: start price, the stage bound and the outlier guard.
     */
    private function checkAmount(Competition $locked, ?int $leaderAmount, ParticipantStanding $standing, OfferStage $stage, int $amount, bool $confirmOutlier): void
    {
        $start = $locked->start_price_minor;

        // 13. Start price (every stage): tender ceiling, auction opening.
        if ($start !== null && OfferRules::isWorse($locked, $amount, $start)) {
            throw new ApiException('offer_start_price', 'bidding.errors.offer_start_price', 422, details: ['start_price_minor' => $start]);
        }

        // 14. Stage bound.
        if ($stage === OfferStage::Live || $stage === OfferStage::Initial) {
            // `initial` forces must_beat = own (§7.3).
            $reference = $stage === OfferStage::Live && $locked->must_beat === MustBeat::Best && $leaderAmount !== null
                ? $leaderAmount
                : $standing->current_amount_minor;

            if ($reference !== null) {
                $required = OfferRules::requiredAmount($locked, $reference);

                if (OfferRules::isWorse($locked, $amount, $required)) {
                    throw new ApiException('offer_step_not_met', 'bidding.errors.offer_step_not_met', 422, details: ['required_amount_minor' => $required]);
                }
            }
        } elseif ($stage === OfferStage::Bafo) {
            $reference = $standing->bafo_reference_amount_minor;

            if ($reference !== null && OfferRules::isWorse($locked, $amount, $reference)) {
                throw new ApiException('offer_bafo_worse_than_reference', 'bidding.errors.offer_bafo_worse_than_reference', 422, details: ['reference_amount_minor' => $reference]);
            }
        }

        // 15. Outlier guard, against the own current amount or the start price (never a hidden value).
        $outlierReference = $standing->current_amount_minor ?? $start;
        $threshold = $this->intSetting('bidding.outlier_guard_bps', 2000);

        if ($outlierReference !== null && ! $confirmOutlier && OfferRules::exceedsOutlierGuard($amount, $outlierReference, $threshold)) {
            throw new ApiException('offer_outlier_confirm_required', 'bidding.errors.offer_outlier_confirm_required', 422, details: [
                'change_bps' => OfferRules::changeBps($amount, $outlierReference),
                'reference_amount_minor' => $outlierReference,
            ]);
        }
    }

    /**
     * Step 19 (§7.7): a change of leader inside the window extends the close to
     * `min(max(close, now + by), hard_stop)`; the soft close does not stack.
     */
    private function antiSnipe(Competition $locked, CarbonImmutable $now, bool $leaderChanged, Offer $offer): bool
    {
        if (! $locked->auto_extend_enabled || ! $leaderChanged || $locked->effective_close_at === null) {
            return false;
        }

        $close = $locked->effective_close_at;
        $window = (int) $locked->auto_extend_window_seconds;
        $by = (int) $locked->auto_extend_by_seconds;
        $max = (int) $locked->auto_extend_max;

        if ($now->lessThan($close->subSeconds($window)) || $locked->extension_count >= $max) {
            return false;
        }

        $newClose = $close->max($now->addSeconds($by));

        if ($locked->hard_stop_at !== null) {
            $newClose = $newClose->min($locked->hard_stop_at);
        }

        if (! $newClose->greaterThan($close)) {
            return false;
        }

        $this->timing->extend($locked, $newClose, ExtensionKind::Auto, Actor::system(), $offer->id);

        return true;
    }

    private function findByKey(Participant $participant, string $idempotencyKey): ?Offer
    {
        return Offer::query()
            ->where('participant_id', $participant->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Same amount → the original offer (200, `Idempotent-Replayed: true`); different → 422.
     */
    private function replay(Offer $offer, mixed $amount): SubmittedOffer
    {
        if (self::normalise($amount) !== $offer->amount_minor) {
            throw new ApiException('idempotency_key_reused', status: 422);
        }

        return new SubmittedOffer($offer, replayed: true);
    }

    /**
     * A cached rejection of the same key: the same amount re-throws the same error, another
     * amount is a reused key.
     */
    private function replayRejection(Participant $participant, string $idempotencyKey, mixed $amount): void
    {
        $cached = Cache::get(self::rejectionKey($participant, $idempotencyKey));

        if (! is_array($cached)) {
            return;
        }

        if (self::normalise($amount) !== ($cached['amount_minor'] ?? null)) {
            throw new ApiException('idempotency_key_reused', status: 422);
        }

        /** @var array<string, mixed> $details */
        $details = is_array($cached['details'] ?? null) ? $cached['details'] : [];
        $code = is_string($cached['code'] ?? null) ? $cached['code'] : 'offer_not_accepting';

        throw new ApiException(
            errorCode: $code,
            messageKey: is_string($cached['message_key'] ?? null) ? $cached['message_key'] : null,
            status: is_int($cached['status'] ?? null) ? $cached['status'] : 422,
            details: $details,
        );
    }

    private function recordRejection(
        Competition $competition,
        Participant $participant,
        User $user,
        int $amount,
        string $idempotencyKey,
        Actor $actor,
        CarbonImmutable $receivedAt,
        ?CarbonImmutable $dbTime,
        ?OfferStage $stage,
        ApiException $exception,
    ): void {
        OfferRejection::query()->create([
            'competition_id' => $competition->id,
            'participant_id' => $participant->id,
            'user_id' => $user->id,
            'amount_minor' => $amount,
            'code' => $exception->errorCode,
            'idempotency_key' => $idempotencyKey,
            'stage' => $stage?->value,
            'received_at' => $receivedAt,
            'db_time' => $dbTime,
            'channel' => self::offerChannel($actor)->value,
            'ip' => $actor->ip,
        ]);

        Cache::put(self::rejectionKey($participant, $idempotencyKey), [
            'status' => $exception->status,
            'code' => $exception->errorCode,
            'message_key' => $exception->messageKey,
            'details' => $exception->details,
            'amount_minor' => $amount,
        ], now()->addHours((int) config('bafo.bidding.rejection_cache_hours', 24)));
    }

    public static function rejectionKey(Participant $participant, string $idempotencyKey): string
    {
        return "offer-rej:{$participant->id}:{$idempotencyKey}";
    }

    /**
     * Step 7: a positive integer (a JSON integer or a string of digits).
     */
    private function parseAmount(mixed $amount): int
    {
        $normalised = self::normalise($amount);

        if ($normalised === null || $normalised <= 0) {
            throw new ApiException('offer_amount_invalid', 'bidding.errors.offer_amount_invalid', 422);
        }

        return $normalised;
    }

    private static function normalise(mixed $amount): ?int
    {
        if (is_int($amount)) {
            return $amount;
        }

        if (is_string($amount) && preg_match('/^\d{1,30}$/', $amount) === 1) {
            // Beyond PHP_INT_MAX the value is only ever "too large".
            return strlen(ltrim($amount, '0')) > 18 ? PHP_INT_MAX : (int) $amount;
        }

        return null;
    }

    /**
     * Offers come from the apps (web, ios, android) or, later, the API.
     */
    private static function offerChannel(Actor $actor): Channel
    {
        return in_array($actor->channel, [Channel::Web, Channel::Ios, Channel::Android, Channel::Api], true)
            ? $actor->channel
            : Channel::Web;
    }

    private function intSetting(string $key, int $default): int
    {
        $value = $this->settings->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
