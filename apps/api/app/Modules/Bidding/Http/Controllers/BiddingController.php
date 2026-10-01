<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Shared helpers of the Bidding app v1 controllers: the viewer of a competition (§8.2) with the
 * 404 / 403 split of the contract, and the permission abilities of BiddingPolicy.
 */
abstract class BiddingController extends ApiController
{
    protected function actor(): Actor
    {
        return CurrentActor::get();
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * The viewer, or 404 (existence is never revealed).
     */
    protected function viewer(Competition $competition): Viewer
    {
        return app(ViewerResolver::class)->resolve($competition, $this->actor())
            ?? throw new ApiException('not_found', status: 404);
    }

    /**
     * Issuer-only endpoints: 404 for strangers, 403 for participants and invitees.
     */
    protected function issuerViewer(Competition $competition): Viewer
    {
        $viewer = $this->viewer($competition);

        if (! $viewer->isIssuer()) {
            throw new ApiException('forbidden', status: 403);
        }

        return $viewer;
    }

    /**
     * Participant-only endpoints: 404 for strangers, 403 `not_a_participant` otherwise.
     */
    protected function participantViewer(Competition $competition): Viewer
    {
        $viewer = $this->viewer($competition);

        if (! $viewer->isParticipant() || $viewer->participant === null) {
            throw new ApiException('not_a_participant', 'bidding.errors.not_a_participant', 403);
        }

        return $viewer;
    }

    protected function authorizeAbility(Request $request, string $ability): void
    {
        Gate::forUser($this->user($request))->authorize($ability);
    }
}
