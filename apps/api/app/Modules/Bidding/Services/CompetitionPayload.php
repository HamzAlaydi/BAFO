<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionPresenter;
use App\Modules\Identity\Models\User;
use App\Support\Http\Iso;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;

/**
 * The `Competition` resource returned by `POST …/bafo-round` (API.md §1.6), projected for the
 * viewer by Competitions' CompetitionPresenter (API.md §2.6).
 *
 * CONTRACT-GAP: API.md lists no embeddable presenter for the Competition shape, which
 * Competitions owns. Bidding uses Competitions' CompetitionPresenter; while one of its
 * dependencies is not bound yet (Billing's AccessPolicy during parallel development), the
 * endpoint returns the fields a client needs after starting the round.
 */
final readonly class CompetitionPayload
{
    public function __construct(
        private Container $container,
        private VisibilityProjector $projector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Competition $competition, Viewer $viewer, ?User $user): array
    {
        try {
            $presenter = $this->container->make(CompetitionPresenter::class);
        } catch (BindingResolutionException) {
            return $this->minimal($competition, $viewer);
        }

        return $presenter->present($competition, $viewer, $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimal(Competition $competition, Viewer $viewer): array
    {
        $round = $competition->bafoRound()->first();

        return [
            'id' => $competition->public_id,
            'reference_no' => $competition->reference_no,
            'title' => $competition->title,
            'direction' => $competition->direction->value,
            'format' => $competition->format->value,
            'status' => $competition->status->value,
            'phase' => $competition->phaseAt(Date::now())?->value,
            'bafo_round' => $round !== null ? $this->projector->bafoRound($round) : null,
            'viewer_role' => $viewer->role->value,
            'live' => $viewer->isIssuer() ? $this->projector->issuerSnapshot($competition) : null,
            'server_time' => Iso::format(Date::now()),
        ];
    }
}
