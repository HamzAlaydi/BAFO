<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Contracts\SponsorshipService;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionOpened;
use App\Modules\Competitions\Events\CompetitionPublished;
use App\Modules\Competitions\Jobs\CloseCompetition;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\InvitationSender;
use App\Modules\Competitions\Services\RulesValidator;
use App\Modules\Competitions\Services\ScheduleCalculator;
use App\Modules\Competitions\Services\StateMachine;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a draft (ARCHITECTURE §6.1 T1 draft → scheduled, T2 draft → live). Called by the
 * app and public API, and by Billing after a sponsorship payment (actor = the payer).
 *
 * In one transaction holding the competition row lock:
 *
 *   1. R20 plan (403 `issuer_plan_required`)
 *   2. the derived times (§7.2), then every publish check: rules (422), R17 invitations,
 *      R19 live-event cap (409)
 *   3. draft invitations of e-mails that registered since are bound to their organization, then
 *      R21 `SponsorshipService::reserveForPublish()` (409 `sponsorship_payment_required`),
 *      while the invitations are still drafts
 *   4. `reference_no`, the transition (live when the opening time is now or past), the draft
 *      invitations → sent (tokens, mails, InvitationSent)
 *   5. CompetitionPublished (+ CompetitionOpened); after commit, the close job
 */
final readonly class PublishCompetition
{
    public function __construct(
        private AccessPolicy $access,
        private SponsorshipService $sponsorship,
        private RulesValidator $validator,
        private ScheduleCalculator $schedule,
        private CompetitionStateMachine $stateMachine,
        private InvitationSender $sender,
    ) {}

    public function handle(Competition $c, Actor $actor): Competition
    {
        return DB::transaction(function () use ($c, $actor): Competition {
            $competition = Competition::query()->whereKey($c->id)->lockForUpdate()->firstOrFail();

            if ($competition->status !== CompetitionStatus::Draft) {
                throw StateMachine::invalid($competition->status, CompetitionStatus::Scheduled);
            }

            $competition->load(['organization', 'category']);

            if (! $this->access->canIssue($competition->organization)) {
                throw CreateCompetition::planRequired();
            }

            $now = Date::now();
            $opensNow = $competition->bidding_opens_at === null || $competition->bidding_opens_at->lessThanOrEqualTo($now);
            $opens = $opensNow ? $now : $competition->bidding_opens_at;

            $this->schedule->derive($competition, $opens);

            $drafts = Invitation::query()
                ->where('competition_id', $competition->id)
                ->where('status', InvitationStatus::Draft->value)
                ->orderBy('id')
                ->get();

            $this->validator->validatePublish($competition, $now, $drafts->count());

            // Before the sponsorship quote, so that an invitee with its own plan counts as own_plan.
            $this->bindKnownOrganizations($competition, $drafts);

            $this->sponsorship->reserveForPublish($competition);

            $attributes = [
                'reference_no' => $this->schedule->referenceNumber($competition->direction, $now),
                'published_at' => $now,
            ];

            if ($opensNow) {
                $this->stateMachine->transition($competition, CompetitionStatus::Live, $actor, [
                    ...$attributes,
                    'bidding_opens_at' => $now,
                    'opened_at' => $now,
                ]);
            } else {
                $this->stateMachine->transition($competition, CompetitionStatus::Scheduled, $actor, $attributes);
            }

            $this->sender->sendAll($competition, $drafts, $actor);

            AuditLogger::log('competition.published', $competition, actor: $actor, organizationId: $competition->organization_id);

            event(new CompetitionPublished($competition, $actor));

            if ($opensNow) {
                event(new CompetitionOpened($competition));
            }

            CloseCompetition::scheduleFor($competition);

            return $competition;
        });
    }

    /**
     * An invitee may have registered since the draft invitation was written: bind its
     * organization now (§13.11 e-mail resolution), unless that is the issuer itself.
     *
     * @param  Collection<int, Invitation>  $drafts
     */
    private function bindKnownOrganizations(Competition $competition, $drafts): void
    {
        foreach ($drafts->whereNull('organization_id') as $invitation) {
            $user = User::query()->with('membership')->where('email', $invitation->email)->first();
            $organizationId = $user?->membership?->organization_id;

            if ($organizationId !== null && $organizationId !== $competition->organization_id) {
                $invitation->organization_id = $organizationId;
                $invitation->save();
            }
        }
    }
}
