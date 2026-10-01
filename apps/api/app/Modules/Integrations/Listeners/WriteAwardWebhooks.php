<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Services\Webhooks\WebhookEmitter;
use App\Modules\Integrations\Services\Webhooks\WebhookObjects;

/**
 * Synchronous outbox writers for the award webhooks (ARCHITECTURE §10, API.md §4.1):
 *
 *   Bidding\AwardIssued   → award.issued
 *   Bidding\AwardRevoked  → award.cancelled
 */
final readonly class WriteAwardWebhooks
{
    public function __construct(
        private WebhookEmitter $emitter,
        private WebhookObjects $objects,
    ) {}

    public function issued(object $event): void
    {
        [$award, $competition] = self::read($event);

        $this->emit($competition, $award, WebhookEventType::AwardIssued, $this->objects->awardIssued($award, $competition));
    }

    public function revoked(object $event): void
    {
        [$award, $competition] = self::read($event);

        $this->emit($competition, $award, WebhookEventType::AwardCancelled, $this->objects->awardCancelled($award, $competition));
    }

    /**
     * @return array{0: Award, 1: Competition}
     */
    private static function read(object $event): array
    {
        $award = EventProperty::get($event, 'award', Award::class);
        $competition = EventProperty::optional($event, 'competition', Competition::class)
            ?? Competition::query()->findOrFail($award->competition_id);

        return [$award, $competition];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function emit(Competition $competition, Award $award, WebhookEventType $type, array $object): void
    {
        $issuer = Organization::query()->findOrFail($competition->organization_id);

        $this->emitter->emit($issuer, $type->value, $award, $object);
    }
}
