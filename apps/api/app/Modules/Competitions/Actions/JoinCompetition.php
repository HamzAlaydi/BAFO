<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Events\InvitationJoined;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Competitions\Services\StateMachine;
use App\Modules\Identity\Models\Organization;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /invitations/{invitation}/join` (ARCHITECTURE §13.11): the invitee organization
 * accepts the terms and becomes a participant (the participation lock, R4 §2.5).
 *
 * Preconditions, in order: `accept_terms` (422 `terms_not_accepted`); invitation sent or viewed
 * (409 `invalid_state_transition`); competition scheduled or live before the cut-off
 * (409 `join_deadline_passed`); not already a participant (409 `already_participating`).
 * In the transaction: `AccessPolicy::resolveJoin()` (403 `plan_required`), the participant with
 * a random free alias, the invitation → joined, and the organization's other pending
 * invitations → revoked (`duplicate_organization`).
 */
final readonly class JoinCompetition
{
    public function __construct(
        private AccessPolicy $access,
        private RevokeInvitation $revoke,
    ) {}

    public function handle(Invitation $invitation, bool $acceptTerms, int $userId, Actor $actor): Participant
    {
        if (! $acceptTerms) {
            throw new ApiException(
                errorCode: 'terms_not_accepted',
                messageKey: 'competitions.errors.terms_not_accepted',
                status: 422,
                errors: ['accept_terms' => [self::text('competitions.errors.terms_not_accepted')]],
            );
        }

        return DB::transaction(function () use ($invitation, $userId, $actor): Participant {
            // Lock order (§7.4): the competition row first.
            $competition = Competition::query()->whereKey($invitation->competition_id)->lockForUpdate()->firstOrFail();
            $locked = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $now = Date::now();

            if (! in_array($locked->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true)) {
                throw StateMachine::invalidInvitation($locked->status, InvitationStatus::Joined);
            }

            if (! in_array($competition->status, [CompetitionStatus::Scheduled, CompetitionStatus::Live], true)
                || $competition->invitation_cutoff_at === null
                || $now->greaterThanOrEqualTo($competition->invitation_cutoff_at)) {
                throw new ApiException(
                    errorCode: 'join_deadline_passed',
                    messageKey: 'competitions.errors.join_deadline_passed',
                    status: 409,
                );
            }

            $organization = Organization::query()->findOrFail($locked->organization_id);

            $exists = Participant::query()
                ->where('competition_id', $competition->id)
                ->where('organization_id', $organization->id)
                ->exists();

            if ($exists) {
                throw new ApiException(
                    errorCode: 'already_participating',
                    messageKey: 'competitions.errors.already_participating',
                    status: 409,
                );
            }

            $locked->setRelation('competition', $competition);
            $source = $this->access->resolveJoin($organization, $locked);

            $participant = new Participant;
            $participant->forceFill([
                'competition_id' => $competition->id,
                'organization_id' => $organization->id,
                'invitation_id' => $locked->id,
                'alias_no' => $this->freeAlias($competition),
                'entitlement_source' => $source,
                'terms_version' => $this->termsVersion(),
                'terms_accepted_at' => $now,
                'terms_ip' => $actor->ip,
                'joined_by_user_id' => $userId,
            ])->save();

            $locked->forceFill(['status' => InvitationStatus::Joined, 'joined_at' => $now])->save();

            $duplicates = Invitation::query()
                ->where('competition_id', $competition->id)
                ->where('organization_id', $organization->id)
                ->whereKeyNot($locked->id)
                ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
                ->get();

            foreach ($duplicates as $duplicate) {
                $this->revoke->handle($duplicate, $actor, RevokeReason::DuplicateOrganization);
            }

            // The home feed of the participant organization (§4.5).
            AuditLogger::log('invitation.joined', $locked, meta: [
                'competition_id' => $competition->public_id,
                'participant_id' => $participant->public_id,
                'entitlement_source' => $source->value,
            ], actor: $actor, organizationId: $organization->id);

            event(new InvitationJoined($locked, $participant, $actor));
            event(new CompetitionUpdated($competition, ['invitations'], $actor));

            return $participant;
        });
    }

    /**
     * A random unused alias: 1–99 first, then 100–999 (§5.5). The competition row lock
     * serialises joins, so the choice cannot collide.
     */
    private function freeAlias(Competition $competition): int
    {
        $used = Participant::query()->where('competition_id', $competition->id)->pluck('alias_no')
            ->map(static fn (mixed $alias): int => (int) $alias)->all();
        $free = array_values(array_diff(range(1, 99), $used)) ?: array_values(array_diff(range(100, 999), $used));

        return $free[random_int(0, count($free) - 1)];
    }

    /**
     * The current published `competition_rules` version (Arabic first, then English).
     */
    private function termsVersion(): string
    {
        $document = LegalDocument::latestPublished(LegalDocumentCode::CompetitionRules, 'ar')
            ?? LegalDocument::latestPublished(LegalDocumentCode::CompetitionRules, 'en');

        // CONTRACT-GAP: without a published document the column (not null) records "unpublished".
        return $document !== null ? $document->version : 'unpublished';
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
