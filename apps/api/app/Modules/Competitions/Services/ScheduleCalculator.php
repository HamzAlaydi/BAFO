<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The values derived at publish (ARCHITECTURE §7.2 "Derived values at publish", §5.5).
 */
final readonly class ScheduleCalculator
{
    public function __construct(private CompetitionSettings $settings) {}

    /**
     * Sets effective_close_at, hard_stop_at, final_window_starts_at and invitation_cutoff_at on
     * the (unsaved) model from `$opens` and `scheduled_close_at`.
     */
    public function derive(Competition $competition, CarbonImmutable $opens): void
    {
        $close = $competition->scheduled_close_at ?? $opens;

        $competition->effective_close_at = $close;
        $competition->hard_stop_at = $competition->auto_extend_enabled
            ? $close->addSeconds((int) $competition->auto_extend_max * (int) $competition->auto_extend_by_seconds)
            : null;
        $competition->final_window_starts_at = $competition->final_window_minutes !== null
            ? $close->subMinutes($competition->final_window_minutes)
            : null;

        $cutoff = $competition->final_window_starts_at ?? $close->subMinutes($this->settings->inviteCutoffMinutes());
        $competition->invitation_cutoff_at = $cutoff->max($opens);
    }

    /**
     * `BAFO-{T|A}-{YYYY Riyadh}-{nextval padded to 6}` from `competition_reference_seq` (§5.5).
     */
    public function referenceNumber(Direction $direction, CarbonImmutable $publishedAt): string
    {
        $next = (int) DB::scalar("select nextval('competition_reference_seq')");

        return sprintf(
            'BAFO-%s-%s-%06d',
            $direction === Direction::Tender ? 'T' : 'A',
            $publishedAt->setTimezone('Asia/Riyadh')->format('Y'),
            $next,
        );
    }
}
