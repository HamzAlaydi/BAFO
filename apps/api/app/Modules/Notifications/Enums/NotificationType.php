<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

use App\Modules\Notifications\Notifications\AwardNotSelectedNotification;
use App\Modules\Notifications\Notifications\AwardRevokedNotification;
use App\Modules\Notifications\Notifications\AwardWonNotification;
use App\Modules\Notifications\Notifications\BafoEndedNotification;
use App\Modules\Notifications\Notifications\BafoInvitedNotification;
use App\Modules\Notifications\Notifications\BafoNotification;
use App\Modules\Notifications\Notifications\CommentCreatedNotification;
use App\Modules\Notifications\Notifications\CompetitionCancelledNotification;
use App\Modules\Notifications\Notifications\CompetitionClosedNotification;
use App\Modules\Notifications\Notifications\CompetitionClosingSoonNotification;
use App\Modules\Notifications\Notifications\CompetitionExtendedNotification;
use App\Modules\Notifications\Notifications\CompetitionFinalWindowStartedNotification;
use App\Modules\Notifications\Notifications\CompetitionInvitedNotification;
use App\Modules\Notifications\Notifications\CompetitionNotAwardedNotification;
use App\Modules\Notifications\Notifications\CompetitionOpenedNotification;
use App\Modules\Notifications\Notifications\CompetitionUpdatedNotification;
use App\Modules\Notifications\Notifications\ExportFinishedNotification;
use App\Modules\Notifications\Notifications\ImportFinishedNotification;
use App\Modules\Notifications\Notifications\InvitationDeclinedNotification;
use App\Modules\Notifications\Notifications\InvitationJoinedNotification;
use App\Modules\Notifications\Notifications\InvoiceIssuedNotification;
use App\Modules\Notifications\Notifications\OfferReceivedNotification;
use App\Modules\Notifications\Notifications\OfferVoidedNotification;
use App\Modules\Notifications\Notifications\PaymentFailedNotification;
use App\Modules\Notifications\Notifications\SponsorshipPublishFailedNotification;
use App\Modules\Notifications\Notifications\SponsorshipUnusedPassesNotification;
use App\Modules\Notifications\Notifications\StandingLostLeadNotification;
use App\Modules\Notifications\Notifications\SubscriptionActivatedNotification;
use App\Modules\Notifications\Notifications\SubscriptionExpiredNotification;
use App\Modules\Notifications\Notifications\SubscriptionExpiringNotification;
use App\Modules\Notifications\Notifications\VoucherIssuedNotification;
use App\Modules\Notifications\Notifications\WebhookEndpointDisabledNotification;

/**
 * The notification catalogue (ARCHITECTURE §11.3): 31 types. The value is the `type` stored in
 * `notifications.data` and sent to clients; the channels are the catalogue row. Per-recipient
 * rules (mail to the issuer only, push only without a heartbeat, throttles) narrow these
 * channels when a notification is sent, never widen them.
 */
enum NotificationType: string
{
    case CompetitionInvited = 'competition.invited';
    case CompetitionUpdated = 'competition.updated';
    case CompetitionOpened = 'competition.opened';
    case CompetitionFinalWindowStarted = 'competition.final_window_started';
    case CompetitionClosingSoon = 'competition.closing_soon';
    case CompetitionExtended = 'competition.extended';
    case CompetitionClosed = 'competition.closed';
    case CompetitionCancelled = 'competition.cancelled';
    case CompetitionNotAwarded = 'competition.not_awarded';
    case OfferReceived = 'offer.received';
    case StandingLostLead = 'standing.lost_lead';
    case BafoInvited = 'bafo.invited';
    case BafoEnded = 'bafo.ended';
    case AwardWon = 'award.won';
    case AwardNotSelected = 'award.not_selected';
    case AwardRevoked = 'award.revoked';
    case OfferVoided = 'offer.voided';
    case CommentCreated = 'comment.created';
    case InvitationJoined = 'invitation.joined';
    case InvitationDeclined = 'invitation.declined';
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionExpiring = 'subscription.expiring';
    case SubscriptionExpired = 'subscription.expired';
    case PaymentFailed = 'payment.failed';
    case InvoiceIssued = 'invoice.issued';
    case SponsorshipUnusedPasses = 'sponsorship.unused_passes';
    case SponsorshipPublishFailed = 'sponsorship.publish_failed';
    case VoucherIssued = 'voucher.issued';
    case WebhookEndpointDisabled = 'webhook.endpoint_disabled';
    case ImportFinished = 'import.finished';
    case ExportFinished = 'export.finished';

    /** Label in the given locale (default: the app locale). Key: `notifications.enums.notification_type.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('notifications.enums.notification_type.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }

    /**
     * The lang key of the type: `notifications.<key>.{title, body, mail_subject, mail_intro, mail_action}` (§11.2).
     */
    public function key(): string
    {
        return str_replace('.', '_', $this->value);
    }

    /**
     * The catalogue channels (§11.3 columns In-app, Push, Mail).
     *
     * @return list<DeliveryChannel>
     */
    public function channels(): array
    {
        $inApp = DeliveryChannel::Database;
        $push = DeliveryChannel::Push;
        $mail = DeliveryChannel::Mail;

        return match ($this) {
            self::CompetitionClosingSoon => [$push],
            self::CompetitionInvited,
            self::CompetitionUpdated,
            self::CompetitionOpened,
            self::CompetitionFinalWindowStarted,
            self::CompetitionExtended,
            self::OfferReceived,
            self::StandingLostLead,
            self::BafoEnded,
            self::CommentCreated,
            self::InvitationJoined => [$inApp, $push],
            self::CompetitionClosed,
            self::CompetitionCancelled,
            self::CompetitionNotAwarded,
            self::BafoInvited,
            self::AwardWon,
            self::AwardNotSelected,
            self::AwardRevoked,
            self::OfferVoided,
            self::SubscriptionExpiring => [$inApp, $push, $mail],
            self::SubscriptionActivated,
            self::SubscriptionExpired,
            self::PaymentFailed,
            self::InvoiceIssued,
            self::SponsorshipUnusedPasses,
            self::SponsorshipPublishFailed,
            self::VoucherIssued,
            self::WebhookEndpointDisabled => [$inApp, $mail],
            self::InvitationDeclined,
            self::ImportFinished,
            self::ExportFinished => [$inApp],
        };
    }

    public function sends(DeliveryChannel $channel): bool
    {
        return in_array($channel, $this->channels(), true);
    }

    /**
     * The count placeholder of the rows rendered with `trans_choice` (§11.4 "tc"), if any.
     */
    public function countParam(): ?string
    {
        return match ($this) {
            self::CompetitionClosingSoon => 'minutes',
            self::SubscriptionExpiring => 'days_left',
            default => null,
        };
    }

    /**
     * @return class-string<BafoNotification>
     */
    public function notificationClass(): string
    {
        return match ($this) {
            self::CompetitionInvited => CompetitionInvitedNotification::class,
            self::CompetitionUpdated => CompetitionUpdatedNotification::class,
            self::CompetitionOpened => CompetitionOpenedNotification::class,
            self::CompetitionFinalWindowStarted => CompetitionFinalWindowStartedNotification::class,
            self::CompetitionClosingSoon => CompetitionClosingSoonNotification::class,
            self::CompetitionExtended => CompetitionExtendedNotification::class,
            self::CompetitionClosed => CompetitionClosedNotification::class,
            self::CompetitionCancelled => CompetitionCancelledNotification::class,
            self::CompetitionNotAwarded => CompetitionNotAwardedNotification::class,
            self::OfferReceived => OfferReceivedNotification::class,
            self::StandingLostLead => StandingLostLeadNotification::class,
            self::BafoInvited => BafoInvitedNotification::class,
            self::BafoEnded => BafoEndedNotification::class,
            self::AwardWon => AwardWonNotification::class,
            self::AwardNotSelected => AwardNotSelectedNotification::class,
            self::AwardRevoked => AwardRevokedNotification::class,
            self::OfferVoided => OfferVoidedNotification::class,
            self::CommentCreated => CommentCreatedNotification::class,
            self::InvitationJoined => InvitationJoinedNotification::class,
            self::InvitationDeclined => InvitationDeclinedNotification::class,
            self::SubscriptionActivated => SubscriptionActivatedNotification::class,
            self::SubscriptionExpiring => SubscriptionExpiringNotification::class,
            self::SubscriptionExpired => SubscriptionExpiredNotification::class,
            self::PaymentFailed => PaymentFailedNotification::class,
            self::InvoiceIssued => InvoiceIssuedNotification::class,
            self::SponsorshipUnusedPasses => SponsorshipUnusedPassesNotification::class,
            self::SponsorshipPublishFailed => SponsorshipPublishFailedNotification::class,
            self::VoucherIssued => VoucherIssuedNotification::class,
            self::WebhookEndpointDisabled => WebhookEndpointDisabledNotification::class,
            self::ImportFinished => ImportFinishedNotification::class,
            self::ExportFinished => ExportFinishedNotification::class,
        };
    }
}
