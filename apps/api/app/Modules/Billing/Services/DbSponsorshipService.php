<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\SponsorshipService;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Audit\AuditLogger;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The sponsorship hooks of Competitions (ARCHITECTURE §3.6, §13.5). Both run inside the
 * caller's transaction and lock the sponsorship row; when the funded slots are not enough they
 * throw 409 `sponsorship_payment_required` with the quote and write nothing.
 */
final readonly class DbSponsorshipService implements SponsorshipService
{
    public function __construct(private SponsorshipQuoter $quoter) {}

    public function reserveForPublish(Competition $c): void
    {
        $sponsorship = $this->lockedSponsorship($c);

        if ($sponsorship === null) {
            return;
        }

        // CONTRACT-GAP: §13.5 names the draft invitations. Sent and viewed ones are included too,
        // so the result does not depend on whether the publish flow sends the invitations before
        // or after this call; invitations that already hold a pass are skipped by the quote.
        $candidates = $c->invitations()
            ->whereIn('status', [InvitationStatus::Draft->value, InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->reserve($c, $sponsorship, $candidates);
    }

    public function reserveForInvitations(Competition $c, Collection $invitations): void
    {
        // Invitations of a draft stay drafts: their passes are reserved at publish.
        if ($c->status === CompetitionStatus::Draft) {
            return;
        }

        $sponsorship = $this->lockedSponsorship($c);

        if ($sponsorship === null) {
            return;
        }

        $this->reserve($c, $sponsorship, $invitations->values());
    }

    /**
     * @param  Collection<int, Invitation>  $candidates
     */
    private function reserve(Competition $c, CompetitionSponsorship $sponsorship, Collection $candidates): void
    {
        $quote = $this->quoter->quote($c, $sponsorship, $candidates);

        if ($quote->passesToBuy > 0) {
            throw new ApiException(
                'sponsorship_payment_required',
                'billing.errors.sponsorship_payment_required',
                409,
                replace: ['count' => $quote->passesToBuy],
                details: ['quote' => $quote->toArray()],
            );
        }

        if ($quote->needs === []) {
            return;
        }

        // A slot never assigned to a pass was bought (or granted) for this purpose; once every
        // funded slot has been assigned once, a free slot is one a released pass gave back.
        $assigned = $this->quoter->countPasses($sponsorship, [
            PassStatus::Reserved->value, PassStatus::Joined->value, PassStatus::Released->value, PassStatus::Unused->value,
        ]);
        $now = CarbonImmutable::now();

        foreach ($quote->needs as $invitation) {
            $source = $assigned < $sponsorship->funded_passes ? PassSource::Purchase : PassSource::FreedSlot;
            $assigned++;

            SponsoredPass::query()->create([
                'sponsorship_id' => $sponsorship->id,
                'competition_id' => $c->id,
                'invitation_id' => $invitation->id,
                'organization_id' => $invitation->organization_id,
                'source' => $source,
                'status' => PassStatus::Reserved,
                'reserved_at' => $now,
            ]);
        }

        AuditLogger::log('sponsorship.passes_reserved', $sponsorship, meta: ['count' => count($quote->needs)], organizationId: $c->organization_id);
    }

    private function lockedSponsorship(Competition $c): ?CompetitionSponsorship
    {
        return CompetitionSponsorship::query()
            ->where('competition_id', $c->id)
            ->lockForUpdate()
            ->first();
    }
}
