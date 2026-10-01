<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Events\WebhookEndpointDisabled;
use App\Modules\Integrations\Jobs\DeliverWebhook;
use App\Modules\Integrations\Jobs\DispatchWebhookEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Modules\Integrations\Services\Webhooks\WebhookDeliverer;
use App\Modules\Integrations\Services\Webhooks\WebhookSigner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Integrations\IntegrationsFixtures;

beforeEach(function () {
    config(['bafo.integrations.webhooks.allow_private_targets' => false]);
    IntegrationsFixtures::fakeDns()->map('rebound.example.sa', ['192.168.1.20']);
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
});

/**
 * @return array{0: WebhookDelivery, 1: WebhookEndpoint, 2: WebhookEvent}
 */
function integrationsPendingDelivery(array $endpointAttributes = []): array
{
    $organization = Organization::factory()->apiEnabled()->create();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create($endpointAttributes);
    $event = WebhookEvent::factory()->create([
        'organization_id' => $organization->id,
        'type' => 'award.issued',
        'payload' => [
            'id' => 'placeholder', 'type' => 'award.issued', 'api_version' => 'v1', 'environment' => 'test',
            'occurred_at' => '2026-11-10T09:12:44.000Z', 'organization_id' => $organization->public_id, 'sequence' => 1,
            'data' => ['object' => ['id' => '01jc', 'object' => 'award', 'amount_minor' => 22150000, 'winner' => ['name' => 'شركة الريادة']]],
            'links' => ['object' => 'http://localhost/api/public/v1/awards/01jc'],
        ],
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'webhook_event_id' => $event->id,
        'webhook_endpoint_id' => $endpoint->id,
        'status' => DeliveryStatus::Pending,
        'attempts' => 0,
        'next_attempt_at' => now(),
    ]);

    return [$delivery, $endpoint, $event];
}

function integrationsDeliver(WebhookDelivery $delivery): ?WebhookDelivery
{
    return app(WebhookDeliverer::class)->deliver($delivery->id);
}

it('posts the Standard Webhooks headers and a signature the API.md §4.3 check accepts', function () {
    [$delivery, $endpoint, $event] = integrationsPendingDelivery();
    Http::fake(['*' => Http::response('{"ok":true}', 200)]);

    integrationsDeliver($delivery);

    Http::assertSent(function (HttpRequest $request) use ($endpoint, $event): bool {
        $body = $request->body();
        $id = $request->header('webhook-id')[0];
        $ts = $request->header('webhook-timestamp')[0];
        $sigHeader = $request->header('webhook-signature')[0];
        $secret = $endpoint->secret;

        // The consumer example of API.md §4.3, verbatim.
        $key = base64_decode(substr($secret, 6));
        $expected = base64_encode(hash_hmac('sha256', "$id.$ts.$body", $key, true));
        $ok = collect(explode(' ', $sigHeader))
            ->contains(fn ($s) => str_starts_with($s, 'v1,') && hash_equals($expected, substr($s, 3)))
            && abs(time() - (int) $ts) <= 300;

        return $ok
            && $request->method() === 'POST'
            && $request->url() === $endpoint->url
            && $id === $event->public_id
            && $ts === (string) now()->getTimestamp()
            && $request->header('content-type')[0] === 'application/json'
            && $request->header('user-agent')[0] === 'BAFO-Webhooks/1.0'
            && WebhookSigner::verify($secret, $id, $ts, $sigHeader, $body, time())
            && array_keys(json_decode($body, true))[0] === 'id'
            && str_contains($body, 'شركة الريادة');
    });
});

it('marks a 2xx as succeeded and clears the endpoint failure streak', function () {
    [$delivery, $endpoint] = integrationsPendingDelivery(['failing_since' => now()->subHour()]);
    Http::fake(['*' => Http::response(str_repeat('x', 5000), 202)]);

    $delivery = integrationsDeliver($delivery);

    expect($delivery?->status)->toBe(DeliveryStatus::Succeeded)
        ->and($delivery?->attempts)->toBe(1)
        ->and($delivery?->last_http_status)->toBe(202)
        ->and($delivery?->succeeded_at)->not->toBeNull()
        ->and($delivery?->next_attempt_at)->toBeNull()
        ->and(strlen((string) $delivery?->last_response_excerpt))->toBe(2048)
        ->and($endpoint->refresh()->failing_since)->toBeNull()
        ->and($endpoint->last_success_at)->not->toBeNull();
});

it('retries after 5 s, 1 min, 5 min, 30 min, 2 h, 5 h, 10 h and 14 h, then fails after 9 attempts', function () {
    [$delivery, $endpoint] = integrationsPendingDelivery();
    Http::fake(['*' => Http::response('down', 500)]);
    $delays = [];

    for ($attempt = 1; $attempt <= 9; $attempt++) {
        $delivery = integrationsDeliver($delivery);

        expect($delivery?->attempts)->toBe($attempt)->and($delivery?->last_http_status)->toBe(500)->and($delivery?->last_error)->toBe('HTTP 500');

        if ($attempt < 9) {
            expect($delivery?->status)->toBe(DeliveryStatus::Pending);
            $delays[] = (int) now()->diffInSeconds($delivery?->next_attempt_at);

            // Not due yet: nothing is sent.
            expect(integrationsDeliver($delivery))->toBeNull();

            $this->travelTo($delivery?->next_attempt_at);
        }
    }

    expect($delays)->toBe([5, 60, 300, 1800, 7200, 18000, 36000, 50400])
        ->and($delivery?->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery?->failed_at)->not->toBeNull()
        ->and($delivery?->next_attempt_at)->toBeNull()
        ->and(array_sum($delays) / 3600)->toBeGreaterThan(24.0);

    Http::assertSentCount(9);
    Queue::assertPushed(DeliverWebhook::class, 8);
    expect($endpoint->refresh()->failing_since)->not->toBeNull();
});

it('treats timeouts, network errors and redirects as failures', function (Closure $response, string $error) {
    [$delivery] = integrationsPendingDelivery();
    Http::fake(['*' => $response]);

    $delivery = integrationsDeliver($delivery);

    expect($delivery?->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery?->attempts)->toBe(1)
        ->and($delivery?->last_error)->toBe($error);
})->with([
    'timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 15001 milliseconds'), 'timeout'],
    'connection refused' => [fn () => throw new ConnectionException('cURL error 7: Failed to connect'), 'connection_error'],
    'redirect' => [fn () => Http::response('', 302, ['Location' => 'https://elsewhere.example.sa']), 'HTTP 302 (redirects are not followed)'],
]);

it('disables the endpoint on 410 Gone', function () {
    Event::fake([WebhookEndpointDisabled::class]);
    [$delivery, $endpoint] = integrationsPendingDelivery();
    Http::fake(['*' => Http::response('', 410)]);

    $delivery = integrationsDeliver($delivery);

    expect($delivery?->status)->toBe(DeliveryStatus::Failed)
        ->and($endpoint->refresh()->status)->toBe(WebhookEndpointStatus::Disabled)
        ->and($endpoint->disabled_reason)->toBe(WebhookDisabledReason::Failing);

    Event::assertDispatched(WebhookEndpointDisabled::class, fn (WebhookEndpointDisabled $e) => $e->endpoint->is($endpoint));
});

it('disables an endpoint failing for more than five days', function () {
    Event::fake([WebhookEndpointDisabled::class]);
    [$delivery, $endpoint] = integrationsPendingDelivery(['failing_since' => now()->subDays(5)->subMinute()]);
    Http::fake(['*' => Http::response('', 503)]);

    integrationsDeliver($delivery);

    expect($endpoint->refresh()->status)->toBe(WebhookEndpointStatus::Disabled)
        ->and($endpoint->disabled_reason)->toBe(WebhookDisabledReason::Failing);
    Event::assertDispatchedTimes(WebhookEndpointDisabled::class, 1);
});

it('keeps an endpoint failing for less than five days', function () {
    Event::fake([WebhookEndpointDisabled::class]);
    [$delivery, $endpoint] = integrationsPendingDelivery(['failing_since' => now()->subDays(4)]);
    Http::fake(['*' => Http::response('', 503)]);

    integrationsDeliver($delivery);

    expect($endpoint->refresh()->status)->toBe(WebhookEndpointStatus::Active);
    Event::assertNotDispatched(WebhookEndpointDisabled::class);
});

it('blocks a target that now resolves to a private address, without sending', function () {
    [$delivery] = integrationsPendingDelivery(['url' => 'https://rebound.example.sa/hooks']);
    Http::fake();

    $delivery = integrationsDeliver($delivery);

    expect($delivery?->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery?->last_error)->toBe('blocked_target')
        ->and($delivery?->last_http_status)->toBeNull();
    Http::assertNothingSent();
});

it('does not send to a disabled or deleted endpoint', function (Closure $change) {
    [$delivery, $endpoint] = integrationsPendingDelivery();
    $change($endpoint);
    Http::fake();

    $delivery = integrationsDeliver($delivery);

    expect($delivery?->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery?->last_error)->toBe('endpoint_disabled')
        ->and($delivery?->attempts)->toBe(0);
    Http::assertNothingSent();
})->with([
    'disabled' => [fn (WebhookEndpoint $endpoint) => $endpoint->forceFill(['status' => 'disabled', 'disabled_reason' => 'manual'])->save()],
    'deleted' => [fn (WebhookEndpoint $endpoint) => $endpoint->delete()],
]);

it('never sends a delivery twice at the same time', function () {
    [$delivery] = integrationsPendingDelivery();
    $delivery->forceFill(['next_attempt_at' => now()->addMinute()])->save();
    Http::fake();

    expect(integrationsDeliver($delivery))->toBeNull();
    Http::assertNothingSent();
});

it('sends the same bytes on every attempt', function () {
    [$delivery] = integrationsPendingDelivery();
    $bodies = [];
    Http::fake(function (HttpRequest $request) use (&$bodies) {
        $bodies[] = $request->body();

        return Http::response('', 500);
    });

    integrationsDeliver($delivery);
    $this->travel(5)->seconds();
    integrationsDeliver($delivery);

    expect($bodies)->toHaveCount(2)->and($bodies[0])->toBe($bodies[1]);
});

it('sweeps stranded events and due deliveries every minute', function () {
    [$delivery] = integrationsPendingDelivery();
    $stranded = WebhookEvent::factory()->create(['created_at' => now()->subMinute()]);
    $fresh = WebhookEvent::factory()->create();

    test()->artisan('integrations:dispatch-webhooks')->assertSuccessful();

    Queue::assertPushed(DispatchWebhookEvent::class, fn (DispatchWebhookEvent $job) => $job->webhookEventId === $stranded->id);
    Queue::assertNotPushed(DispatchWebhookEvent::class, fn (DispatchWebhookEvent $job) => $job->webhookEventId === $fresh->id);
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->webhookDeliveryId === $delivery->id);
});

it('prunes events older than 30 days', function () {
    WebhookEvent::factory()->create(['created_at' => now()->subDays(31)]);
    $kept = WebhookEvent::factory()->create(['created_at' => now()->subDays(29)]);

    test()->artisan('integrations:prune')->assertSuccessful();

    expect(WebhookEvent::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('schedules the outbox sweep every minute and the prune daily', function () {
    $expression = fn (string $command): ?string => collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, $command))?->expression;

    expect($expression('integrations:dispatch-webhooks'))->toBe('* * * * *')
        ->and($expression('integrations:prune'))->toBe('0 0 * * *');
});
