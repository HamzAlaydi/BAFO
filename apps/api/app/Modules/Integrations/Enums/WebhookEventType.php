<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

/**
 * The v1 webhook catalogue (API.md §4.1): 12 domain types plus `webhook.test`.
 *
 * CONTRACT-GAP: the catalogue is a table in API.md with no enum name; `webhook_events.type` and
 * `webhook_endpoints.event_types` store these values, so they get an enum like every other
 * enumerated column (CONVENTIONS §2.1).
 */
enum WebhookEventType: string
{
    case CompetitionPublished = 'competition.published';
    case CompetitionExtended = 'competition.extended';
    case CompetitionClosed = 'competition.closed';
    case CompetitionOffersOpened = 'competition.offers_opened';
    case CompetitionCancelled = 'competition.cancelled';
    case CompetitionNotAwarded = 'competition.not_awarded';
    case InvitationAccepted = 'invitation.accepted';
    case InvitationDeclined = 'invitation.declined';
    case OfferSubmitted = 'offer.submitted';
    case OfferUpdated = 'offer.updated';
    case AwardIssued = 'award.issued';
    case AwardCancelled = 'award.cancelled';
    case WebhookTest = 'webhook.test';

    /** Subscribes an endpoint to every type. */
    public const string WILDCARD = '*';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /** Label in the given locale (default: the app locale). Key: `integrations.enums.webhook_event_type.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('integrations.enums.webhook_event_type.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
