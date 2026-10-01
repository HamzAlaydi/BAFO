<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Contracts\CompetitionTimingService;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Jobs\CloseCompetition;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;

/**
 * Moves `effective_close_at` (ARCHITECTURE §3.6 `CompetitionTimingService`). The caller holds the
 * competition row lock inside a transaction (§7.4 lock order).
 */
final class CompetitionTiming implements CompetitionTimingService
{
    public function extend(
        Competition $locked,
        CarbonImmutable $newCloseAt,
        ExtensionKind $kind,
        Actor $actor,
        ?int $triggeredByOfferId = null,
        ?string $reason = null,
    ): CompetitionExtension {
        $previous = $locked->effective_close_at ?? $locked->scheduled_close_at ?? $newCloseAt;

        $locked->effective_close_at = $newCloseAt;
        $locked->extension_count = $locked->extension_count + 1;
        $locked->save();

        $extension = CompetitionExtension::query()->create([
            'competition_id' => $locked->id,
            'kind' => $kind,
            'previous_close_at' => $previous,
            'new_close_at' => $newCloseAt,
            'triggered_by_offer_id' => $kind === ExtensionKind::Auto ? $triggeredByOfferId : null,
            'actor_user_id' => $actor->userId,
            'actor_admin_id' => $actor->adminId,
            'reason' => $reason,
        ]);

        event(new CompetitionExtended($locked, $extension, $actor));

        CloseCompetition::scheduleFor($locked);

        return $extension;
    }
}
