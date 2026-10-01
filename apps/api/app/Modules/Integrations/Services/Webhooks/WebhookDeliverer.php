<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Events\WebhookEndpointDisabled;
use App\Modules\Integrations\Jobs\DeliverWebhook;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One delivery attempt (ARCHITECTURE §14.5 "Deliver", API.md §4.3–§4.4).
 *
 *   - A due `pending` delivery is claimed with a short lease on `next_attempt_at`, so a job and
 *     the scheduler never send it twice at the same time.
 *   - The endpoint must still be active, and its URL passes the SSRF guard (else `failed`,
 *     `blocked_target`); the request connects to the checked address.
 *   - POST with the Standard Webhooks headers, the same body bytes on every attempt, a 15 s
 *     timeout and no redirects.
 *   - 2xx → `succeeded`. Anything else → retry after 5 s, 1 min, 5 min, 30 min, 2 h, 5 h, 10 h,
 *     14 h (9 attempts), then `failed`. 410 Gone fails the delivery and disables the endpoint;
 *     five days of continuous failure disable it too (`WebhookEndpointDisabled`).
 */
final readonly class WebhookDeliverer
{
    public const string USER_AGENT = 'BAFO-Webhooks/1.0';

    public function __construct(private WebhookUrlGuard $guard) {}

    /**
     * Returns the delivery after the attempt, or null when it was not due (or not pending).
     */
    public function deliver(int $deliveryId): ?WebhookDelivery
    {
        if (! $this->claim($deliveryId)) {
            return null;
        }

        $delivery = WebhookDelivery::query()->with('event')->find($deliveryId);
        $endpoint = WebhookEndpoint::withTrashed()->find($delivery?->webhook_endpoint_id);

        if ($delivery === null || $delivery->event === null || $endpoint === null) {
            return null;
        }

        if ($endpoint->trashed() || ! $endpoint->isActive()) {
            return $this->finishWithoutAttempt($delivery, 'endpoint_disabled');
        }

        try {
            $target = $this->guard->check($endpoint->url);
        } catch (BlockedWebhookTarget $blocked) {
            Log::warning('webhooks.blocked_target', ['delivery_id' => $delivery->public_id, 'reason' => $blocked->reason]);

            return $this->record($delivery->id, new AttemptResult(null, 'blocked_target', null, 0), terminal: true);
        }

        return $this->record($delivery->id, $this->send($delivery->event, $endpoint, $target));
    }

    /**
     * The request body: the stored envelope, encoded the same way on every attempt. JSONB keeps
     * object keys in its own order, so the envelope keys are put back in the API.md §4.2 order.
     */
    public static function body(WebhookEvent $event): string
    {
        $payload = $event->payload;
        $ordered = [];

        foreach (['id', 'type', 'api_version', 'environment', 'occurred_at', 'organization_id', 'sequence', 'data', 'links'] as $key) {
            if (array_key_exists($key, $payload)) {
                $ordered[$key] = $payload[$key];
            }
        }

        return json_encode($ordered + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Seconds before the next attempt after `$attempts` failed attempts, or null when exhausted.
     */
    public static function backoffAfter(int $attempts): ?int
    {
        /** @var list<int> $backoff */
        $backoff = config('bafo.integrations.webhooks.backoff_seconds');
        $maxAttempts = (int) config('bafo.integrations.webhooks.max_attempts');

        if ($attempts >= $maxAttempts) {
            return null;
        }

        return $backoff[min(max($attempts - 1, 0), count($backoff) - 1)];
    }

    private function claim(int $deliveryId): bool
    {
        $now = Date::now();

        return WebhookDelivery::query()
            ->whereKey($deliveryId)
            ->where('status', DeliveryStatus::Pending)
            ->where(static fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->update(['next_attempt_at' => $now->addSeconds((int) config('bafo.integrations.webhooks.lease_seconds'))]) === 1;
    }

    private function send(WebhookEvent $event, WebhookEndpoint $endpoint, WebhookTarget $target): AttemptResult
    {
        $body = self::body($event);
        $timestamp = Date::now()->getTimestamp();
        $started = hrtime(true);

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->withHeaders([
                    'webhook-id' => $event->public_id,
                    'webhook-timestamp' => (string) $timestamp,
                    'webhook-signature' => WebhookSigner::sign($endpoint->secret, $event->public_id, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->timeout((int) config('bafo.integrations.webhooks.timeout_seconds'))
                ->withoutRedirecting()
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [$target->curlResolveEntry()]]])
                ->post($target->url);
        } catch (ConnectionException $e) {
            $error = str_contains(strtolower($e->getMessage()), 'timed out') ? 'timeout' : 'connection_error';

            return new AttemptResult(null, $error, null, self::elapsedMs($started));
        }

        $status = $response->status();

        return new AttemptResult(
            status: $status,
            error: $response->successful() ? null : ($response->redirect() ? "HTTP {$status} (redirects are not followed)" : "HTTP {$status}"),
            excerpt: self::excerpt($response->body()),
            durationMs: self::elapsedMs($started),
        );
    }

    private function record(int $deliveryId, AttemptResult $result, bool $terminal = false): WebhookDelivery
    {
        [$delivery, $disabled] = DB::transaction(static function () use ($deliveryId, $result, $terminal): array {
            $delivery = WebhookDelivery::query()->lockForUpdate()->findOrFail($deliveryId);
            $endpoint = WebhookEndpoint::withTrashed()->lockForUpdate()->findOrFail($delivery->webhook_endpoint_id);
            $now = Date::now();

            $delivery->fill([
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => $now,
                'last_http_status' => $result->status,
                'last_error' => $result->error === null ? null : mb_substr($result->error, 0, 500),
                'last_response_excerpt' => $result->excerpt,
                'last_duration_ms' => $result->durationMs,
            ]);

            if ($result->succeeded()) {
                $delivery->fill(['status' => DeliveryStatus::Succeeded, 'succeeded_at' => $now, 'failed_at' => null, 'next_attempt_at' => null]);
                $endpoint->fill(['last_success_at' => $now, 'failing_since' => null])->save();
                $delivery->save();

                return [$delivery, false];
            }

            $endpoint->fill(['last_failure_at' => $now, 'failing_since' => $endpoint->failing_since ?? $now]);
            $retryIn = $terminal || $result->status === 410 ? null : self::backoffAfter($delivery->attempts);

            $delivery->fill($retryIn === null
                ? ['status' => DeliveryStatus::Failed, 'failed_at' => $now, 'next_attempt_at' => null]
                : ['status' => DeliveryStatus::Pending, 'next_attempt_at' => $now->addSeconds($retryIn)]);
            $delivery->save();

            $disable = $endpoint->isActive() && ($result->status === 410 || self::failingTooLong($endpoint->failing_since, $now));

            if ($disable) {
                $endpoint->fill(['status' => WebhookEndpointStatus::Disabled, 'disabled_reason' => WebhookDisabledReason::Failing]);
            }

            $endpoint->save();

            if ($disable) {
                event(new WebhookEndpointDisabled($endpoint));
            }

            return [$delivery, $disable];
        });

        if ($disabled) {
            Log::warning('webhooks.endpoint_disabled', ['endpoint_id' => $delivery->webhook_endpoint_id, 'http_status' => $result->status]);
        }

        if ($delivery->status === DeliveryStatus::Pending && $delivery->next_attempt_at !== null) {
            DeliverWebhook::dispatch($delivery->id)->delay($delivery->next_attempt_at);
        }

        return $delivery;
    }

    /**
     * The endpoint was disabled or deleted after the delivery was created: it is not sent.
     *
     * CONTRACT-GAP: §14.5 says only "re-check that the endpoint is active". The delivery ends
     * `failed` with `last_error = endpoint_disabled` and no attempt is counted; it can be
     * redelivered once the endpoint is active again.
     */
    private function finishWithoutAttempt(WebhookDelivery $delivery, string $error): WebhookDelivery
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Failed,
            'failed_at' => Date::now(),
            'next_attempt_at' => null,
            'last_error' => $error,
        ])->save();

        return $delivery;
    }

    private static function failingTooLong(?CarbonImmutable $failingSince, CarbonImmutable $now): bool
    {
        return $failingSince !== null
            && $failingSince->lessThan($now->subDays((int) config('bafo.integrations.webhooks.disable_after_days')));
    }

    private static function excerpt(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $excerpt = mb_strcut($body, 0, (int) config('bafo.integrations.webhooks.response_excerpt_bytes'), 'UTF-8');

        // PostgreSQL text rejects NUL bytes and invalid UTF-8.
        return str_replace("\0", '', mb_convert_encoding($excerpt, 'UTF-8', 'UTF-8'));
    }

    private static function elapsedMs(int|float $started): int
    {
        return (int) intdiv((int) (hrtime(true) - $started), 1_000_000);
    }
}
