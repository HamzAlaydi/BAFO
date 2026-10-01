<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Services\Webhooks\WebhookEmitter;
use App\Modules\Integrations\Services\Webhooks\WebhookObjects;

/**
 * Synchronous outbox writer for `Bidding\OfferAccepted` (ARCHITECTURE §10): `offer.submitted`
 * for the participant's first offer, `offer.updated` for later ones (API.md §4.1). The event's
 * `context->isFirstOfferOfParticipant` decides; without it the ledger does.
 */
final readonly class WriteOfferWebhooks
{
    public function __construct(
        private WebhookEmitter $emitter,
        private WebhookObjects $objects,
    ) {}

    public function accepted(object $event): void
    {
        $offer = EventProperty::get($event, 'offer', Offer::class);
        $competition = EventProperty::optional($event, 'competition', Competition::class)
            ?? Competition::query()->findOrFail($offer->competition_id);

        $type = self::isFirstOffer($event, $offer) ? WebhookEventType::OfferSubmitted : WebhookEventType::OfferUpdated;
        $issuer = Organization::query()->findOrFail($competition->organization_id);

        $this->emitter->emit($issuer, $type->value, $offer, $this->objects->offer($offer, $competition));
    }

    private static function isFirstOffer(object $event, Offer $offer): bool
    {
        $context = EventProperty::value($event, 'context');
        $first = is_object($context) ? EventProperty::bool($context, 'isFirstOfferOfParticipant') : null;

        return $first ?? ! Offer::query()
            ->where('participant_id', $offer->participant_id)
            ->where('seq', '<', $offer->seq)
            ->exists();
    }
}
