<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Services\Webhooks\WebhookEmitter;
use App\Modules\Integrations\Services\Webhooks\WebhookObjects;

/**
 * Synchronous outbox writers for the competition webhooks (ARCHITECTURE §10, API.md §4.1). They
 * run inside the producer's transaction and only write `webhook_events` rows.
 *
 *   Competitions\CompetitionPublished           → competition.published
 *   Competitions\CompetitionExtended            → competition.extended
 *   Competitions\CompetitionClosed              → competition.closed
 *   Bidding\OffersUnsealed                      → competition.offers_opened
 *   Competitions\CompetitionCancelled           → competition.cancelled
 *   Competitions\CompetitionClosedWithoutAward  → competition.not_awarded
 */
final readonly class WriteCompetitionWebhooks
{
    public function __construct(
        private WebhookEmitter $emitter,
        private WebhookObjects $objects,
    ) {}

    public function published(object $event): void
    {
        $competition = self::competition($event);

        $this->emit($competition, WebhookEventType::CompetitionPublished, $this->objects->competitionPublished($competition));
    }

    public function extended(object $event): void
    {
        $competition = self::competition($event);
        $extension = EventProperty::get($event, 'extension', CompetitionExtension::class);

        $this->emit($competition, WebhookEventType::CompetitionExtended, $this->objects->competitionExtended($competition, $extension));
    }

    public function closed(object $event): void
    {
        $competition = self::competition($event);

        $this->emit($competition, WebhookEventType::CompetitionClosed, $this->objects->competitionClosed($competition));
    }

    public function offersOpened(object $event): void
    {
        $competition = self::competition($event);

        $this->emit($competition, WebhookEventType::CompetitionOffersOpened, $this->objects->competitionOffersOpened($competition));
    }

    public function cancelled(object $event): void
    {
        $competition = self::competition($event);

        $this->emit($competition, WebhookEventType::CompetitionCancelled, $this->objects->competitionCancelled($competition));
    }

    public function notAwarded(object $event): void
    {
        $competition = self::competition($event);

        $this->emit($competition, WebhookEventType::CompetitionNotAwarded, $this->objects->competitionNotAwarded($competition));
    }

    private static function competition(object $event): Competition
    {
        return EventProperty::get($event, 'competition', Competition::class);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function emit(Competition $competition, WebhookEventType $type, array $object): void
    {
        $issuer = Organization::query()->findOrFail($competition->organization_id);

        $this->emitter->emit($issuer, $type->value, $competition, $object);
    }
}
