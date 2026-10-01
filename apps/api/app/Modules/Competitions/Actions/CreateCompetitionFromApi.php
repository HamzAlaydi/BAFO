<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Actions\ConfigureSponsorship;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Competitions\Data\CompetitionInput;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Public `POST /competitions` (API.md §3.4, flow F2): the draft, its optional invitations and
 * its optional sponsorship configuration, all or nothing.
 *
 * CONTRACT-GAP: §3.6 lists no Billing entry point for the sponsorship configuration; the public
 * body's `sponsorship` goes through Billing's own `ConfigureSponsorship` Action (the one behind
 * `PUT …/sponsorship`), so the rules stay in Billing.
 */
final readonly class CreateCompetitionFromApi
{
    public function __construct(
        private CreateCompetition $create,
        private InviteParticipants $invite,
        private ConfigureSponsorship $sponsorship,
    ) {}

    /**
     * @param  list<array{email?: string|null, name?: string|null, organization_id?: int|null, vendor_id?: int|null, sponsored?: bool}>  $invitations
     * @param  array{mode: SponsorshipMode|null, max_passes: int|null}|null  $sponsorship
     */
    public function handle(Organization $organization, CompetitionInput $input, array $invitations, ?array $sponsorship, Actor $actor): Competition
    {
        return DB::transaction(function () use ($organization, $input, $invitations, $sponsorship, $actor): Competition {
            $competition = $this->create->handle($organization, $input, $actor);

            if ($sponsorship !== null && $sponsorship['mode'] !== null) {
                $competition->setRelation('organization', $organization);
                $this->sponsorship->handle($competition, $sponsorship['mode'], $sponsorship['max_passes'], $actor);
            }

            if ($invitations !== []) {
                $this->invite->handle($competition, $invitations, $actor);
            }

            return $competition;
        });
    }
}
