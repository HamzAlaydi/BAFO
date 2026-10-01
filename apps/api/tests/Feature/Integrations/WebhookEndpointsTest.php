<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Jobs\DeliverWebhook;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Support\Audit\AuditLog;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Integrations\IntegrationsFixtures;

beforeEach(function () {
    config(['bafo.integrations.webhooks.allow_private_targets' => false]);
    IntegrationsFixtures::fakeDns()->map('internal.example.sa', ['10.0.0.5'])->map('mixed.example.sa', ['203.0.113.7', '127.0.0.1']);
    Http::preventStrayRequests();
});

const INTEGRATIONS_ENDPOINTS = '/api/app/v1/integrations/webhook-endpoints';

it('creates an endpoint and shows its whsec_ secret once', function () {
    [$organization, $user] = IntegrationsFixtures::signIn();

    $response = test()->postJson(INTEGRATIONS_ENDPOINTS, [
        'url' => 'https://erp.example.sa/bafo/webhooks',
        'event_types' => ['award.issued', 'competition.closed'],
        'description' => 'ERP gateway',
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => [
            'id', 'url', 'description', 'event_types', 'status', 'disabled_reason', 'failing_since', 'last_success_at',
            'last_failure_at', 'created_at', 'secret',
        ]]);

    $endpoint = WebhookEndpoint::query()->sole();
    $secret = $response->json('data.secret');

    expect($secret)->toStartWith('whsec_')
        ->and(strlen((string) base64_decode(substr($secret, 6), true)))->toBe(32)
        ->and($endpoint->secret)->toBe($secret)
        ->and($endpoint->getRawOriginal('secret'))->not->toContain('whsec_')
        ->and($endpoint->organization_id)->toBe($organization->id)
        ->and($endpoint->created_by_user_id)->toBe($user->id)
        ->and(AuditLog::query()->where('action', 'webhook_endpoint.created')->count())->toBe(1);

    test()->getJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id)->assertOk()->assertJsonMissingPath('data.secret');
});

it('stores a subscription that contains * as ["*"]', function () {
    IntegrationsFixtures::signIn();

    test()->postJson(INTEGRATIONS_ENDPOINTS, ['url' => 'https://erp.example.sa/h', 'event_types' => ['award.issued', '*']])
        ->assertCreated()
        ->assertJsonPath('data.event_types', ['*']);
});

it('refuses unsafe URLs with webhook_url_invalid', function (string $url, string $reason) {
    IntegrationsFixtures::signIn();

    test()->postJson(INTEGRATIONS_ENDPOINTS, ['url' => $url, 'event_types' => ['*']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'webhook_url_invalid')
        ->assertJsonPath('details.reason', $reason)
        ->assertJsonValidationErrors(['url']);

    expect(WebhookEndpoint::query()->count())->toBe(0);
})->with([
    'http' => ['http://erp.example.sa/h', 'scheme'],
    'credentials' => ['https://user:pass@erp.example.sa/h', 'credentials'],
    'low port' => ['https://erp.example.sa:22/h', 'port'],
    'loopback literal' => ['https://127.0.0.1/h', 'private_address'],
    'metadata address' => ['https://169.254.169.254/latest', 'private_address'],
    'private DNS answer' => ['https://internal.example.sa/h', 'private_address'],
    'one private address among several' => ['https://mixed.example.sa/h', 'private_address'],
    'IPv6 loopback' => ['https://[::1]/h', 'private_address'],
]);

it('allows private http targets locally when configured', function () {
    config(['bafo.integrations.webhooks.allow_private_targets' => true]);
    IntegrationsFixtures::signIn();

    test()->postJson(INTEGRATIONS_ENDPOINTS, ['url' => 'http://localhost:9999/webhooks', 'event_types' => ['*']])->assertCreated();
});

it('validates the event types', function () {
    IntegrationsFixtures::signIn();

    test()->postJson(INTEGRATIONS_ENDPOINTS, ['url' => 'not a url', 'event_types' => ['award.won']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url', 'event_types.0']);
    test()->postJson(INTEGRATIONS_ENDPOINTS, ['url' => 'https://erp.example.sa/h', 'event_types' => []])
        ->assertJsonValidationErrors(['event_types']);
});

it('disables by hand and re-enables clearing failing_since', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create(['failing_since' => now()->subDay()]);

    test()->patchJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id, ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.disabled_reason', 'manual');

    test()->patchJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id, ['status' => 'active', 'url' => 'https://new.example.sa/h'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.disabled_reason', null)
        ->assertJsonPath('data.failing_since', null)
        ->assertJsonPath('data.url', 'https://new.example.sa/h');

    test()->patchJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id, ['url' => 'https://internal.example.sa/h'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'webhook_url_invalid');
});

it('deletes an endpoint softly', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create();

    test()->deleteJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id)->assertNoContent();

    expect(WebhookEndpoint::query()->count())->toBe(0)
        ->and(WebhookEndpoint::withTrashed()->count())->toBe(1);
    test()->getJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id)->assertNotFound();
});

it('sends a signed test event to that endpoint only, whatever its subscription', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->for($organization)->eventTypes(['award.issued'])->create();
    WebhookEndpoint::factory()->for($organization)->create();
    Http::fake(['*' => Http::response('ok', 200)]);

    $eventId = test()->postJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id.'/test')
        ->assertStatus(202)
        ->json('data.event_id');

    $event = WebhookEvent::query()->sole();
    $delivery = WebhookDelivery::query()->sole();

    expect($eventId)->toBe($event->public_id)
        ->and($event->type)->toBe('webhook.test')
        ->and($event->payload['data']['object'])->toBe(['id' => $endpoint->public_id, 'object' => 'webhook_endpoint', 'message' => 'BAFO test event'])
        ->and($event->payload['links']['object'])->toEndWith('/api/public/v1/webhook-endpoints/'.$endpoint->public_id)
        ->and($delivery->webhook_endpoint_id)->toBe($endpoint->id)
        ->and($delivery->status)->toBe(DeliveryStatus::Succeeded);

    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $request) => $request->url() === $endpoint->url && $request->hasHeader('webhook-signature'));
});

it('rotates the secret', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create();
    $old = $endpoint->secret;

    $secret = test()->postJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id.'/rotate-secret')->assertOk()->json('data.secret');

    expect($secret)->toStartWith('whsec_')->not->toBe($old)
        ->and($endpoint->refresh()->secret)->toBe($secret);
});

it('lists deliveries newest first, filters them and redelivers a failed one', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create();
    $failed = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'status' => DeliveryStatus::Failed, 'attempts' => 9, 'failed_at' => now(), 'last_http_status' => 500,
    ]);
    $succeeded = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create(['status' => DeliveryStatus::Succeeded, 'attempts' => 1]);

    test()->getJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id.'/deliveries')
        ->assertOk()
        ->assertJsonPath('data.0.id', $succeeded->public_id)
        ->assertJsonPath('data.1.id', $failed->public_id)
        ->assertJsonStructure(['data' => [['id', 'event' => ['id', 'type', 'occurred_at'], 'status', 'attempts', 'next_attempt_at',
            'last_attempt_at', 'last_http_status', 'last_error', 'last_response_excerpt', 'last_duration_ms', 'succeeded_at', 'failed_at']]]);

    test()->getJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id.'/deliveries?status=failed')->assertJsonCount(1, 'data');

    Queue::fake();

    test()->postJson('/api/app/v1/integrations/webhook-deliveries/'.$failed->public_id.'/redeliver')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.attempts', 9)
        ->assertJsonPath('data.failed_at', null);

    Queue::assertPushed(DeliverWebhook::class);

    test()->postJson('/api/app/v1/integrations/webhook-deliveries/'.$succeeded->public_id.'/redeliver')
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_state_transition')
        ->assertJsonPath('details.from', 'succeeded');
});

it('lists the event catalogue in the request language', function () {
    IntegrationsFixtures::signIn();

    $types = test()->getJson('/api/app/v1/integrations/webhook-event-types', ['Accept-Language' => 'en'])->assertOk()->json('data');

    expect(array_column($types, 'type'))->toHaveCount(13)->toContain('award.issued', 'offer.submitted', 'webhook.test')
        ->and($types[0])->toBe(['type' => 'competition.published', 'description' => 'A competition was published']);
});

it('hides endpoints and deliveries of other organizations', function () {
    IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->create();
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create(['status' => DeliveryStatus::Failed]);

    test()->getJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id)->assertNotFound();
    test()->postJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id.'/test')->assertNotFound();
    test()->getJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id.'/deliveries')->assertNotFound();
    test()->postJson('/api/app/v1/integrations/webhook-deliveries/'.$delivery->public_id.'/redeliver')->assertNotFound();
    test()->getJson(INTEGRATIONS_ENDPOINTS)->assertOk()->assertJsonCount(0, 'data');
});

it('requires integrations.manage', function () {
    IntegrationsFixtures::signIn(OrgRole::Member);

    test()->postJson(INTEGRATIONS_ENDPOINTS, ['url' => 'https://erp.example.sa/h', 'event_types' => ['*']])->assertForbidden();
});

it('manages endpoints through the public API with webhooks:manage', function () {
    [$plain, $client] = IntegrationsFixtures::apiKey(scopes: [ApiScope::WebhooksManage]);
    $headers = IntegrationsFixtures::bearer($plain);

    $created = test()->postJson('/api/public/v1/webhook-endpoints', ['url' => 'https://erp.example.sa/h', 'event_types' => ['*']],
        [...$headers, 'Idempotency-Key' => 'endpoint-create-1'])->assertCreated();

    $endpoint = WebhookEndpoint::query()->sole();

    $created->assertHeader('Location', url('/api/public/v1/webhook-endpoints/'.$endpoint->public_id));
    expect($created->json('data.secret'))->toStartWith('whsec_')
        ->and($endpoint->created_by_api_client_id)->toBe($client->id)
        ->and($endpoint->organization_id)->toBe($client->organization_id);

    test()->getJson('/api/public/v1/webhook-endpoints', $headers)->assertOk()->assertJsonCount(1, 'data');
    test()->getJson('/api/public/v1/webhook-event-types', $headers)->assertOk()->assertJsonCount(13, 'data');
    test()->patchJson('/api/public/v1/webhook-endpoints/'.$endpoint->public_id, ['description' => 'x'], $headers)->assertOk();
    test()->getJson('/api/public/v1/webhook-endpoints/'.$endpoint->public_id.'/deliveries', $headers)
        ->assertOk()->assertJsonPath('meta.pagination.type', 'cursor');

    Http::fake(['*' => Http::response('', 204)]);
    test()->postJson('/api/public/v1/webhook-endpoints/'.$endpoint->public_id.'/test', [], [...$headers, 'Idempotency-Key' => 'endpoint-test-1'])
        ->assertStatus(202);
    test()->postJson('/api/public/v1/webhook-endpoints/'.$endpoint->public_id.'/rotate-secret', [], [...$headers, 'Idempotency-Key' => 'endpoint-rotate-1'])
        ->assertOk();

    $foreign = WebhookEndpoint::factory()->create();
    test()->getJson('/api/public/v1/webhook-endpoints/'.$foreign->public_id, $headers)->assertNotFound();

    test()->deleteJson('/api/public/v1/webhook-endpoints/'.$endpoint->public_id, [], $headers)->assertNoContent();
});

it('needs webhooks:manage on the public API', function () {
    [$plain] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead]);

    test()->getJson('/api/public/v1/webhook-endpoints', IntegrationsFixtures::bearer($plain))
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');
});

it('records manual disablement with the reason enum', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create();

    test()->patchJson(INTEGRATIONS_ENDPOINTS.'/'.$endpoint->public_id, ['status' => 'disabled'])->assertOk();

    expect($endpoint->refresh()->status)->toBe(WebhookEndpointStatus::Disabled)
        ->and($endpoint->disabled_reason)->toBe(WebhookDisabledReason::Manual);
});
