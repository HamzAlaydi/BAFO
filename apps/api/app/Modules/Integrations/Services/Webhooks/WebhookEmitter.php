<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Jobs\DispatchWebhookEvent;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Support\Http\Iso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The webhook outbox writer (ARCHITECTURE §14.5). Called only from the synchronous Integrations
 * listeners (§10), inside the producer's transaction:
 *
 *   - it writes only if the organization has an active endpoint subscribed to the type (or `*`);
 *   - `sequence` = max + 1 per (subject_type, subject_id), serialised by a transaction-scoped
 *     advisory lock on the subject;
 *   - `payload` is the full API.md §4.2 envelope;
 *   - DispatchWebhookEvent is queued after commit (`webhooks`).
 */
final readonly class WebhookEmitter
{
    public const string API_VERSION = 'v1';

    public function __construct(private WebhookObjects $objects) {}

    /**
     * @param  array<string, mixed>  $object  the `data.object` of the type (API.md §4.1)
     */
    public function emit(Organization $org, string $type, Model $subject, array $object): void
    {
        if (! $this->hasSubscriber($org->id, $type)) {
            return;
        }

        $this->record($org, $type, $subject, $object);
    }

    /**
     * `webhook.test` for one endpoint, whatever its subscription (§14.5 "Test").
     */
    public function emitTest(WebhookEndpoint $endpoint): WebhookEvent
    {
        $organization = Organization::query()->findOrFail($endpoint->organization_id);

        return $this->record($organization, WebhookEventType::WebhookTest->value, $endpoint, $this->objects->test($endpoint));
    }

    public function hasSubscriber(int $organizationId, string $type): bool
    {
        return WebhookEndpoint::query()
            ->where('organization_id', $organizationId)
            ->where('status', WebhookEndpointStatus::Active)
            ->where(static function ($query) use ($type): void {
                $query->whereJsonContains('event_types', WebhookEventType::WILDCARD)
                    ->orWhereJsonContains('event_types', $type);
            })
            ->exists();
    }

    /**
     * The public API URL of the object (API.md §4.2 `links.object`).
     *
     * CONTRACT-GAP: invitations and offers have no single-object GET in public v1; their link is
     * the competition's invitation or offer list. `webhook.test` links to the endpoint resource.
     *
     * @param  array<string, mixed>  $object
     */
    public static function linkFor(string $type, array $object): string
    {
        $base = rtrim((string) config('app.url'), '/').'/api/public/v1/';
        $id = self::stringOf($object['id'] ?? null);
        $competitionId = self::stringOf($object['competition_id'] ?? null);

        return $base.match (explode('.', $type)[0]) {
            'competition' => 'competitions/'.$id,
            'invitation' => 'competitions/'.$competitionId.'/invitations',
            'offer' => 'competitions/'.$competitionId.'/offers',
            'award' => 'awards/'.$id,
            default => 'webhook-endpoints/'.$id,
        };
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function record(Organization $org, string $type, Model $subject, array $object): WebhookEvent
    {
        return DB::transaction(static function () use ($org, $type, $subject, $object): WebhookEvent {
            $subjectType = $subject->getMorphClass();
            $subjectId = (int) $subject->getKey();

            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['webhook_sequence:'.$subjectType.':'.$subjectId]);

            $sequence = 1 + (int) WebhookEvent::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->max('sequence');

            $publicId = WebhookEvent::newPublicId();
            $occurredAt = Date::now();

            $event = new WebhookEvent;
            $event->forceFill([
                'public_id' => $publicId,
                'organization_id' => $org->id,
                'type' => $type,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'sequence' => $sequence,
                'payload' => [
                    'id' => $publicId,
                    'type' => $type,
                    'api_version' => self::API_VERSION,
                    'environment' => ApiKeyGenerator::environment(),
                    'occurred_at' => Iso::format($occurredAt),
                    'organization_id' => $org->public_id,
                    'sequence' => $sequence,
                    'data' => ['object' => $object],
                    'links' => ['object' => self::linkFor($type, $object)],
                ],
                'occurred_at' => $occurredAt,
            ])->save();

            DispatchWebhookEvent::dispatch($event->id)->afterCommit();

            return $event;
        });
    }

    private static function stringOf(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
