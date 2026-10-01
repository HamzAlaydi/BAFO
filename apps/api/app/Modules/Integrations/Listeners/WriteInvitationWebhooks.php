<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Services\Webhooks\WebhookEmitter;
use App\Modules\Integrations\Services\Webhooks\WebhookObjects;

/**
 * Synchronous outbox writers for the invitation webhooks (ARCHITECTURE §10, API.md §4.1),
 * delivered to the issuer:
 *
 *   Competitions\InvitationJoined    → invitation.accepted
 *   Competitions\InvitationDeclined  → invitation.declined
 */
final readonly class WriteInvitationWebhooks
{
    public function __construct(
        private WebhookEmitter $emitter,
        private WebhookObjects $objects,
    ) {}

    public function accepted(object $event): void
    {
        $invitation = EventProperty::get($event, 'invitation', Invitation::class);
        $participant = EventProperty::get($event, 'participant', Participant::class);
        $competition = Competition::query()->findOrFail($invitation->competition_id);

        $this->emit($competition, $invitation, WebhookEventType::InvitationAccepted,
            $this->objects->invitationAccepted($invitation, $participant, $competition));
    }

    public function declined(object $event): void
    {
        $invitation = EventProperty::get($event, 'invitation', Invitation::class);
        $competition = Competition::query()->findOrFail($invitation->competition_id);

        $this->emit($competition, $invitation, WebhookEventType::InvitationDeclined,
            $this->objects->invitationDeclined($invitation, $competition));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function emit(Competition $competition, Invitation $invitation, WebhookEventType $type, array $object): void
    {
        $issuer = Organization::query()->findOrFail($competition->organization_id);

        $this->emitter->emit($issuer, $type->value, $invitation, $object);
    }
}
