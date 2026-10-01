<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Competition;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The competition rules of ARCHITECTURE §7.2 (R1–R19). "Save" checks run on create and update;
 * "publish" checks run all together at publish. Field problems are 422 `validation_failed` with
 * messages `competitions.validation.<rule>` keyed by the API path (`rules.must_beat`,
 * `scheduled_close_at`, …). R14's organization flag is 403 `auction_not_enabled`, R17 is
 * 422 `min_participants_not_met` / `max_participants_exceeded`, and R19 is
 * 409 `live_event_capacity_reached`. R20 (plan) and R21 (sponsorship) are checked by the Actions
 * through the Billing contracts.
 *
 * The checks read a Competition model holding the merged (possibly unsaved) values, with its
 * `organization` and `category` relations.
 */
final readonly class RulesValidator
{
    public function __construct(private CompetitionSettings $settings) {}

    /**
     * Save checks: R1–R4, R6–R14, R16 (order only).
     *
     * @throws ApiException auction_not_enabled (403)
     * @throws ValidationException
     */
    public function validateSave(Competition $competition): void
    {
        $this->assertAuctionEnabled($competition);

        $errors = $this->saveErrors($competition);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Publish checks: every save check again, plus R5, R11 (duration), R15, R16, R18, then R17
     * and R19.
     *
     * @throws ApiException auction_not_enabled, min_participants_not_met, max_participants_exceeded,
     *                      live_event_capacity_reached
     * @throws ValidationException
     */
    public function validatePublish(Competition $competition, CarbonImmutable $now, int $draftInvitations): void
    {
        $this->assertAuctionEnabled($competition);

        $errors = $this->saveErrors($competition);

        // R5: an auction needs its opening price.
        // CONTRACT-GAP: API.md §1.4 lists `start_price_minor` next to `rules.*` among the publish
        // errors; it is keyed `rules.start_price_minor`, its input path, like every rules field.
        if ($competition->direction === Direction::Auction && $competition->start_price_minor === null) {
            $this->add($errors, 'start_price_minor', 'auction_start_price_required');
        }

        // R15: the "Other" category needs its text.
        if ($competition->category?->is_other === true && blank($competition->category_other_text)) {
            $errors['category_other_text'][] = $this->message('category_other_required');
        }

        // R18: a description.
        if (blank($competition->description)) {
            $errors['description'][] = $this->message('description_required');
        }

        // R16 at publish strictness, and R11 against the bidding duration.
        $opens = $competition->bidding_opens_at ?? $now;
        $close = $competition->scheduled_close_at;

        if ($close === null) {
            $errors['scheduled_close_at'][] = $this->message('close_required');
        } else {
            $this->publishScheduleErrors($errors, $competition, $opens, $close, $now);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->assertParticipantCount($competition, $draftInvitations);
        $this->assertLiveCapacity($competition, $opens);
    }

    /**
     * R16 for a schedule change of a scheduled competition (API.md §1.4 PATCH: "re-check R16 at
     * publish-time strictness").
     *
     * @throws ValidationException
     */
    public function validateSchedule(Competition $competition, CarbonImmutable $now): void
    {
        $errors = [];
        $close = $competition->scheduled_close_at;

        if ($close === null) {
            $errors['scheduled_close_at'][] = $this->message('close_required');
        } else {
            $this->publishScheduleErrors($errors, $competition, $competition->bidding_opens_at ?? $now, $close, $now);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * R17: draft invitations ≥ min_participants and ≤ competitions.max_participants.
     *
     * @throws ApiException
     */
    public function assertParticipantCount(Competition $competition, int $draftInvitations): void
    {
        if ($draftInvitations < $competition->min_participants) {
            throw new ApiException(
                errorCode: 'min_participants_not_met',
                messageKey: 'competitions.errors.min_participants_not_met',
                status: 422,
                replace: ['required' => $competition->min_participants, 'current' => $draftInvitations],
                details: ['required' => $competition->min_participants, 'current' => $draftInvitations],
            );
        }

        $max = $this->settings->maxParticipants();

        if ($draftInvitations > $max) {
            throw self::maxParticipantsExceeded($max);
        }
    }

    public static function maxParticipantsExceeded(int $max): ApiException
    {
        return new ApiException(
            errorCode: 'max_participants_exceeded',
            messageKey: 'competitions.errors.max_participants_exceeded',
            status: 422,
            replace: ['max' => $max],
            details: ['max' => $max],
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function saveErrors(Competition $competition): array
    {
        $errors = [];
        $sealed = $competition->format === Format::Sealed;
        $granularity = $competition->amount_granularity_minor;

        // R1: sealed ⇒ no must_beat, no rank, no prices, no auto-extend, no final window.
        if ($sealed) {
            if ($competition->must_beat !== null) {
                $this->add($errors, 'must_beat', 'sealed_must_beat');
            }
            if ($competition->rank_visibility !== RankVisibility::None) {
                $this->add($errors, 'rank_visibility', 'sealed_rank_visibility');
            }
            if ($competition->show_prices) {
                $this->add($errors, 'show_prices', 'sealed_show_prices');
            }
            if ($competition->auto_extend_enabled) {
                $this->add($errors, 'auto_extend_enabled', 'sealed_auto_extend');
            }
            if ($competition->final_window_minutes !== null) {
                $this->add($errors, 'final_window_minutes', 'sealed_final_window');
            }
        } elseif ($competition->must_beat === null) {
            // R2: live ⇒ must_beat own or best.
            $this->add($errors, 'must_beat', 'live_must_beat_required');
        }

        // R3: must beat the leading offer ⇒ prices shown (anti-probing).
        if ($competition->must_beat === MustBeat::Best && ! $competition->show_prices) {
            $this->add($errors, 'must_beat', 'must_beat_best_requires_prices');
        }

        // R4: publishing the winning amount ⇒ prices shown.
        if ($competition->result_publication === ResultPublication::OutcomeAndAmount && ! $competition->show_prices) {
            $this->add($errors, 'result_publication', 'amount_publication_requires_prices');
        }

        // R9: whole riyals or halalas.
        if (! in_array($granularity, [1, 100], true)) {
            $this->add($errors, 'amount_granularity_minor', 'granularity_values');
            $granularity = 100;
        }

        // R8: prices and the absolute step are positive multiples of the granularity, within the maximum.
        $maxAmount = $this->settings->maxAmountMinor();

        foreach (['start_price_minor', 'reserve_price_minor', 'min_step_minor'] as $column) {
            $amount = $competition->getAttribute($column);

            if (! is_int($amount)) {
                continue;
            }

            if ($amount <= 0 || $amount % $granularity !== 0) {
                $this->add($errors, $column, 'amount_granularity', ['granularity' => $granularity]);
            } elseif ($amount > $maxAmount) {
                $this->add($errors, $column, 'amount_too_large', ['max' => $maxAmount]);
            }
        }

        // R6: tender reserve ≤ ceiling; auction reserve ≥ opening price.
        $start = $competition->start_price_minor;
        $reserve = $competition->reserve_price_minor;

        if ($start !== null && $reserve !== null) {
            if ($competition->direction === Direction::Tender && $reserve > $start) {
                $this->add($errors, 'reserve_price_minor', 'reserve_above_start');
            } elseif ($competition->direction === Direction::Auction && $reserve < $start) {
                $this->add($errors, 'reserve_price_minor', 'reserve_below_start');
            }
        }

        // R7: one step kind; bps 1–5000; the absolute step below the start price.
        if ($competition->min_step_minor !== null && $competition->min_step_bps !== null) {
            $this->add($errors, 'min_step_bps', 'single_step_kind');
        }

        if ($competition->min_step_bps !== null && ($competition->min_step_bps < 1 || $competition->min_step_bps > 5000)) {
            $this->add($errors, 'min_step_bps', 'step_bps_range', ['min' => 1, 'max' => 5000]);
        }

        if ($competition->min_step_minor !== null && $start !== null && $competition->min_step_minor >= $start) {
            $this->add($errors, 'min_step_minor', 'step_below_start');
        }

        // R10: auto-extend window, step and count within the bounds.
        if ($competition->auto_extend_enabled && ! $sealed) {
            $bounds = $this->settings->autoExtendBounds();

            $this->range($errors, $competition->auto_extend_window_seconds, 'auto_extend_window_seconds', $bounds['window_min'], $bounds['window_max']);
            $this->range($errors, $competition->auto_extend_by_seconds, 'auto_extend_by_seconds', $bounds['by_min'], $bounds['by_max']);
            $this->range($errors, $competition->auto_extend_max, 'auto_extend_max', $bounds['max_min'], $bounds['max_max']);
        }

        // R11: the final window within its bounds.
        if ($competition->final_window_minutes !== null && ! $sealed) {
            $bounds = $this->settings->finalWindowBounds();
            $this->range($errors, $competition->final_window_minutes, 'final_window_minutes', $bounds['min'], $bounds['max']);
        }

        // R12: the BAFO round duration.
        if ($competition->bafo_round_enabled) {
            $bounds = $this->settings->bafoDurationBounds();
            $this->range($errors, $competition->bafo_duration_minutes, 'bafo_duration_minutes', $bounds['min'], $bounds['max']);
        }

        // R13: min participants 1–50.
        $this->range($errors, $competition->min_participants, 'min_participants', 1, 50);

        // R14: the category must allow auctions.
        if ($competition->direction === Direction::Auction && $competition->category?->auction_allowed === false) {
            $errors['category_id'][] = $this->message('auction_category_not_allowed');
        }

        // R16 at save: when both are set, the close is after the opening.
        if ($competition->bidding_opens_at !== null && $competition->scheduled_close_at !== null
            && $competition->scheduled_close_at->lessThanOrEqualTo($competition->bidding_opens_at)) {
            $errors['scheduled_close_at'][] = $this->message('close_after_opening');
        }

        return $errors;
    }

    /**
     * R16 (publish) and R11 (final window shorter than the bidding duration).
     *
     * @param  array<string, list<string>>  $errors
     */
    private function publishScheduleErrors(array &$errors, Competition $competition, CarbonImmutable $opens,
        CarbonImmutable $close, CarbonImmutable $now): void
    {
        if ($opens->lessThan($now->subSeconds(60))) {
            $errors['bidding_opens_at'][] = $this->message('opens_in_past');
        }

        $durationMinutes = intdiv($close->getTimestamp() - $opens->getTimestamp(), 60);
        $minMinutes = $this->settings->minDurationMinutes();
        $maxDays = $this->settings->maxDurationDays();

        if ($close->lessThanOrEqualTo($opens)) {
            $errors['scheduled_close_at'][] = $this->message('close_after_opening');
        } elseif ($durationMinutes < $minMinutes) {
            $errors['scheduled_close_at'][] = $this->message('duration_too_short', ['minutes' => $minMinutes]);
        } elseif ($close->greaterThan($opens->addDays($maxDays))) {
            $errors['scheduled_close_at'][] = $this->message('duration_too_long', ['days' => $maxDays]);
        }

        if ($competition->final_window_minutes !== null && $competition->format === Format::Live
            && $competition->final_window_minutes >= $durationMinutes) {
            $this->add($errors, 'final_window_minutes', 'final_window_duration');
        }
    }

    /**
     * R14: auctions need the organization's `auction_enabled` flag.
     */
    private function assertAuctionEnabled(Competition $competition): void
    {
        if ($competition->direction === Direction::Auction && $competition->organization?->auction_enabled !== true) {
            throw new ApiException(
                errorCode: 'auction_not_enabled',
                messageKey: 'competitions.errors.auction_not_enabled',
                status: 403,
            );
        }
    }

    /**
     * R19: fewer than `bidding.max_concurrent_live` scheduled or live competitions overlap
     * `[opens, coalesce(hard_stop_at, scheduled_close_at)]` of this one.
     */
    private function assertLiveCapacity(Competition $competition, CarbonImmutable $opens): void
    {
        $end = $competition->hard_stop_at ?? $competition->scheduled_close_at ?? $opens;

        $overlapping = Competition::query()
            ->whereIn('status', [CompetitionStatus::Scheduled->value, CompetitionStatus::Live->value])
            ->whereKeyNot($competition->id)
            ->where('bidding_opens_at', '<=', $end)
            ->where(DB::raw('coalesce(hard_stop_at, scheduled_close_at)'), '>=', $opens)
            ->count();

        if ($overlapping >= $this->settings->maxConcurrentLive()) {
            throw new ApiException(
                errorCode: 'live_event_capacity_reached',
                messageKey: 'competitions.errors.live_event_capacity_reached',
                status: 409,
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function range(array &$errors, ?int $value, string $column, int $min, int $max): void
    {
        if ($value === null || $value < $min || $value > $max) {
            $this->add($errors, $column, 'between', ['min' => $min, 'max' => $max]);
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, scalar>  $replace
     */
    private function add(array &$errors, string $column, string $rule, array $replace = []): void
    {
        $errors[RulesMapper::pathOf($column)][] = $this->message($rule, $replace);
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private function message(string $rule, array $replace = []): string
    {
        $message = __('competitions.validation.'.$rule, $replace);

        return is_string($message) ? $message : $rule;
    }
}
