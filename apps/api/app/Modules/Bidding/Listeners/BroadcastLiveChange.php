<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Listeners;

use App\Modules\Bidding\Data\LastChange;
use App\Modules\Bidding\Enums\LiveChangeKind;
use App\Modules\Bidding\Events\AwardIssued;
use App\Modules\Bidding\Events\AwardRevoked;
use App\Modules\Bidding\Events\BafoRoundEnded;
use App\Modules\Bidding\Events\BafoRoundStarted;
use App\Modules\Bidding\Events\OfferVoided;
use App\Modules\Bidding\Services\LiveBroadcaster;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Broadcasts the engine's own changes to the issuer and every participant (§7.10, kinds `bafo`,
 * `award`, `void`). Their Actions already bumped the version in the same transaction.
 */
final class BroadcastLiveChange implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'live';

    public function __construct(private readonly LiveBroadcaster $broadcaster) {}

    public function handle(BafoRoundStarted|BafoRoundEnded|AwardIssued|AwardRevoked|OfferVoided $event): void
    {
        $kind = match (true) {
            $event instanceof BafoRoundStarted, $event instanceof BafoRoundEnded => LiveChangeKind::Bafo,
            $event instanceof AwardIssued, $event instanceof AwardRevoked => LiveChangeKind::Award,
            default => LiveChangeKind::Void,
        };

        $competition = Competition::query()->find($event->competition->id);

        if ($competition !== null) {
            $this->broadcaster->toEveryone($competition, LastChange::of($kind));
        }
    }
}
