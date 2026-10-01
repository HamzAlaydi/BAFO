<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The permission side of the Bidding actions (ARCHITECTURE §8.1, §8.3), registered as Gate
 * abilities by BiddingServiceProvider:
 *
 *   bidding.submit-offers   participation.submit_offers   submit an offer, heartbeat
 *   bidding.award           competitions.award            start BAFO, award, revoke
 *
 * Who sees a competition (issuer, participant, invitee, or 404) is resolved per request with
 * Competitions' ViewerResolver (§8.2).
 */
final class BiddingPolicy
{
    public function submitOffers(User $user): Response
    {
        return $user->hasPermission(Permission::ParticipationSubmitOffers) ? Response::allow() : Response::deny();
    }

    public function award(User $user): Response
    {
        return $user->hasPermission(Permission::CompetitionsAward) ? Response::allow() : Response::deny();
    }
}
