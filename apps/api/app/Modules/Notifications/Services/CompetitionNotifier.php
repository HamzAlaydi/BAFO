<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\Delivery;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Data\ParticipantRecipient;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationRoutes;
use App\Support\Http\Iso;

/**
 * Competition notifications (ARCHITECTURE §11.3, rows `competition.*`, `comment.created`,
 * `invitation.*`), called by the listeners of the Competitions events (§10). Each method returns
 * the number of users notified.
 */
final readonly class CompetitionNotifier
{
    /**
     * The `CompetitionUpdated` fields that notify participants (§10: fields ⊂ {title, description, schedule, attachments}).
     *
     * @var list<string>
     */
    public const array UPDATE_FIELDS = ['title', 'description', 'schedule', 'attachments'];

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private CompetitionAudience $audience,
        private Recipients $recipients,
        private LiveHeartbeats $heartbeats,
    ) {}

    /**
     * `competition.invited`: the users of a known invitee organization. Unknown organizations
     * only get the token mail, which Competitions sends.
     */
    public function invited(Invitation $invitation): int
    {
        if ($invitation->organization_id === null) {
            return 0;
        }

        $competition = $invitation->competition;

        return $this->dispatcher->send(
            NotificationType::CompetitionInvited,
            $this->payload($competition, NotificationRoutes::competition($competition), [
                'issuer_name' => $competition->organization->name,
                'sponsored' => $this->isSponsored($invitation),
            ]),
            Delivery::toEach($this->recipients->members($invitation->organization_id)),
        );
    }

    /**
     * `competition.updated`: participant users, at most once per 10 minutes per competition.
     *
     * @param  list<string>  $fields
     */
    public function updated(Competition $competition, array $fields): int
    {
        if ($competition->isDraft() || array_intersect($fields, self::UPDATE_FIELDS) === []) {
            return 0;
        }

        $deliveries = [];

        foreach ($this->audience->participantUsers($competition) as $recipient) {
            if ($this->allow('competition.updated', $competition, $recipient->user, 'competition_updated')) {
                $deliveries[] = new Delivery($recipient->user);
            }
        }

        return $this->dispatcher->send(
            NotificationType::CompetitionUpdated,
            $this->payload($competition, NotificationRoutes::competition($competition)),
            $deliveries,
        );
    }

    /**
     * An addendum on a published competition is a `competition.updated` (§10 `AttachmentAdded`).
     * The listener is queued: a draft deleted (soft delete) in the meantime has no competition
     * any more, and there is nobody to notify.
     */
    public function attachmentAdded(CompetitionAttachment $attachment): int
    {
        $competition = $attachment->competition;

        return $competition === null ? 0 : $this->updated($competition, ['attachments']);
    }

    /** `competition.opened`: participant users. */
    public function opened(Competition $competition): int
    {
        return $this->dispatcher->send(
            NotificationType::CompetitionOpened,
            $this->payload($competition, NotificationRoutes::competitionLive($competition)),
            $this->toParticipants($competition),
        );
    }

    /** `competition.final_window_started`: participant users, with the closing time. */
    public function finalWindowStarted(Competition $competition): int
    {
        return $this->dispatcher->send(
            NotificationType::CompetitionFinalWindowStarted,
            $this->payload($competition, NotificationRoutes::competitionLive($competition), [
                'close_time' => Iso::format($competition->effective_close_at),
            ]),
            $this->toParticipants($competition),
        );
    }

    /**
     * `competition.closing_soon` (push only): participant users without a heartbeat. Competitions
     * announces each threshold once (`notified_thresholds`).
     */
    public function closingSoon(Competition $competition, int $minutes): int
    {
        $deliveries = [];

        foreach ($this->audience->participantUsers($competition) as $recipient) {
            if (! $this->isWatching($recipient)) {
                $deliveries[] = new Delivery($recipient->user);
            }
        }

        return $this->dispatcher->send(
            NotificationType::CompetitionClosingSoon,
            $this->payload($competition, NotificationRoutes::competitionLive($competition), ['minutes' => $minutes]),
            $deliveries,
        );
    }

    /**
     * `competition.extended`: participant users and the issuer team. Manual and admin extensions
     * always notify. Anti-sniping (auto) extensions notify each user at most once per 2 minutes,
     * and push only to participants who are not on the live screen.
     */
    public function extended(Competition $competition, CompetitionExtension $extension): int
    {
        $auto = $extension->kind === ExtensionKind::Auto;
        $deliveries = [];

        foreach ($this->audience->participantUsers($competition) as $recipient) {
            if (! $auto) {
                $deliveries[] = new Delivery($recipient->user);
            } elseif ($this->allow('competition.extended', $competition, $recipient->user, 'competition_extended_auto')) {
                $deliveries[] = new Delivery($recipient->user, $this->isWatching($recipient) ? [DeliveryChannel::Push] : []);
            }
        }

        foreach ($this->audience->issuerTeam($competition) as $user) {
            if (! $auto || $this->allow('competition.extended', $competition, $user, 'competition_extended_auto')) {
                $deliveries[] = new Delivery($user);
            }
        }

        return $this->dispatcher->send(
            NotificationType::CompetitionExtended,
            $this->payload($competition, NotificationRoutes::competitionLive($competition), [
                'close_time' => Iso::format($extension->new_close_at),
            ]),
            $deliveries,
        );
    }

    /** `competition.closed`: the issuer team (with mail) and participant users (no mail). */
    public function closed(Competition $competition): int
    {
        return $this->dispatcher->send(
            NotificationType::CompetitionClosed,
            $this->payload($competition, NotificationRoutes::competition($competition)),
            [
                ...Delivery::toEach($this->audience->issuerTeam($competition)),
                ...$this->toParticipants($competition, [DeliveryChannel::Mail]),
            ],
        );
    }

    /**
     * `competition.cancelled`: participant users and the users of invitee organizations whose
     * invitation was still open (sent or viewed), with the reason.
     */
    public function cancelled(Competition $competition): int
    {
        $invitees = $competition->invitations()
            ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            ->whereNotNull('organization_id')
            ->pluck('organization_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $deliveries = $this->toParticipants($competition);

        foreach ($this->recipients->membersByOrganization($invitees) as $users) {
            array_push($deliveries, ...Delivery::toEach($users));
        }

        return $this->dispatcher->send(
            NotificationType::CompetitionCancelled,
            $this->payload($competition, NotificationRoutes::competition($competition), [
                'reason' => $this->reason($competition->cancelReason, $competition->cancel_note),
            ]),
            $deliveries,
        );
    }

    /** `competition.not_awarded`: participant users, unless results are not published. */
    public function notAwarded(Competition $competition): int
    {
        if ($competition->result_publication === ResultPublication::None) {
            return 0;
        }

        return $this->dispatcher->send(
            NotificationType::CompetitionNotAwarded,
            $this->payload($competition, NotificationRoutes::competition($competition)),
            $this->toParticipants($competition),
        );
    }

    /**
     * `comment.created`:
     * - a participant question → the issuer team;
     * - an issuer reply → the users of the organization that asked;
     * - an issuer post → every participant user.
     *
     * The author is never notified. At most one push per 5 minutes per competition per user;
     * in-app always.
     */
    public function commentPosted(Comment $comment): int
    {
        $competition = $comment->competition;
        $parent = $comment->parent;

        $users = match (true) {
            ! $comment->is_issuer => $this->audience->issuerTeam($competition)->all(),
            $parent !== null && ! $parent->is_issuer => $this->recipients->members($parent->author_organization_id)->all(),
            default => array_map(
                static fn (ParticipantRecipient $recipient): User => $recipient->user,
                $this->audience->participantUsers($competition),
            ),
        };

        $deliveries = [];

        foreach ($users as $user) {
            if ($user->getKey() === $comment->author_user_id) {
                continue;
            }

            $pushAllowed = $this->allow('comment.created.push', $competition, $user, 'comment_created_push');
            $deliveries[] = new Delivery($user, $pushAllowed ? [] : [DeliveryChannel::Push]);
        }

        return $this->dispatcher->send(
            NotificationType::CommentCreated,
            $this->payload($competition, NotificationRoutes::competitionQa($competition)),
            $deliveries,
        );
    }

    /** `invitation.joined`: the issuer team, with the organization name and whether a pass covers it. */
    public function invitationJoined(Invitation $invitation, Participant $participant): int
    {
        $competition = $invitation->competition;

        return $this->dispatcher->send(
            NotificationType::InvitationJoined,
            $this->payload($competition, NotificationRoutes::competition($competition), [
                'organization_name' => $participant->organization->name,
                'sponsored' => $participant->entitlement_source === EntitlementSource::SponsoredPass,
            ]),
            Delivery::toEach($this->audience->issuerTeam($competition)),
        );
    }

    /** `invitation.declined` (in-app only): the issuer team. */
    public function invitationDeclined(Invitation $invitation): int
    {
        $competition = $invitation->competition;

        return $this->dispatcher->send(
            NotificationType::InvitationDeclined,
            $this->payload($competition, NotificationRoutes::competition($competition), [
                'organization_name' => $invitation->organization->name ?? $invitation->name ?? $invitation->email,
            ]),
            Delivery::toEach($this->audience->issuerTeam($competition)),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function payload(Competition $competition, string $route, array $params = []): NotificationPayload
    {
        return new NotificationPayload(
            [...CompetitionAudience::params($competition), ...$params],
            NotificationSubject::of($competition),
            $route,
        );
    }

    /**
     * @param  list<DeliveryChannel>  $without
     * @return list<Delivery>
     */
    private function toParticipants(Competition $competition, array $without = []): array
    {
        return array_map(
            static fn (ParticipantRecipient $recipient): Delivery => new Delivery($recipient->user, $without),
            $this->audience->participantUsers($competition),
        );
    }

    private function isWatching(ParticipantRecipient $recipient): bool
    {
        return $this->heartbeats->isWatching($recipient->participant->competition_id, $recipient->participant->id);
    }

    private function allow(string $type, Competition $competition, User $user, string $throttle): bool
    {
        return $this->dispatcher->allow("{$type}:{$competition->id}:{$user->getKey()}", Throttles::seconds($throttle));
    }

    /**
     * A pass reserved (or consumed) for the invitation means its fees are covered (R4).
     */
    private function isSponsored(Invitation $invitation): bool
    {
        return SponsoredPass::query()
            ->where('invitation_id', $invitation->id)
            ->whereIn('status', [PassStatus::Reserved->value, PassStatus::Joined->value])
            ->exists();
    }

    /**
     * The close reason in both languages, plus the note when present (§11.4 `:reason`); null
     * when there is neither (rendered as «غير محدد» / "Not specified").
     *
     * @return array{ar: string, en: string}|null
     */
    private function reason(?CloseReason $reason, ?string $note): ?array
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        if ($reason === null) {
            return $note === null ? null : ['ar' => $note, 'en' => $note];
        }

        $suffix = $note === null ? '' : ' — '.$note;

        return [
            'ar' => ($reason->translated('name', 'ar') ?? '').$suffix,
            'en' => ($reason->translated('name', 'en') ?? '').$suffix,
        ];
    }
}
