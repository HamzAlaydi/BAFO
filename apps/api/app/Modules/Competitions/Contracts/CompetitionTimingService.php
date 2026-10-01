<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Contracts;

use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;

/**
 * Moves the close of a live competition (ARCHITECTURE §3.6, §7.7, §7.17). Bidding calls it for
 * anti-sniping (kind `auto`) while it holds the competition row lock; Competitions calls it for
 * manual and admin extensions.
 */
interface CompetitionTimingService
{
    /**
     * The caller holds `SELECT … FOR UPDATE` on the competition row, inside a transaction.
     *
     * Moves `effective_close_at` to `$newCloseAt`, bumps `extension_count`, writes a
     * `competition_extensions` row and dispatches CompetitionExtended. After commit, the
     * CloseCompetition job is dispatched again with the new close as its delay.
     */
    public function extend(
        Competition $locked,
        CarbonImmutable $newCloseAt,
        ExtensionKind $kind,
        Actor $actor,
        ?int $triggeredByOfferId = null,
        ?string $reason = null,
    ): CompetitionExtension;
}
