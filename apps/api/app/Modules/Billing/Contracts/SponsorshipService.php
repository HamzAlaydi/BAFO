<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Collection;

/**
 * Sponsorship hooks used by Competitions (ARCHITECTURE §3.6, §13.5).
 */
interface SponsorshipService
{
    /**
     * Inside the publish TX. Reserves passes for the sponsored invitations from funded slots.
     *
     * @throws ApiException sponsorship_payment_required (409, `details.quote` = SponsorshipQuote) when
     *                      the funded slots are not enough; nothing is written then
     */
    public function reserveForPublish(Competition $c): void;

    /**
     * Inside the invite-more TX: the same semantics for the newly created invitations.
     *
     * @param  Collection<int, Invitation>  $invitations
     *
     * @throws ApiException sponsorship_payment_required (409, `details.quote`)
     */
    public function reserveForInvitations(Competition $c, Collection $invitations): void;
}
