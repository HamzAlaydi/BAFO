<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\IntegrationsServiceProvider;
use App\Modules\Integrations\Listeners\LinkVendorsToOrganization;
use App\Modules\Integrations\Listeners\RevokeOrganizationApiAccess;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;

it('registers the API.md §1.9 app routes', function (string $name, string $method, string $uri, ?string $access) {
    $route = app(Router::class)->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe($uri)
        ->and($route?->methods())->toContain($method);

    if ($access !== null) {
        expect($route?->middleware())->toContain($access);
    }
})->with([
    ['app.v1.vendors.index', 'GET', 'api/app/v1/vendors', null],
    ['app.v1.vendors.store', 'POST', 'api/app/v1/vendors', null],
    ['app.v1.vendors.show', 'GET', 'api/app/v1/vendors/{vendor}', null],
    ['app.v1.vendors.update', 'PATCH', 'api/app/v1/vendors/{vendor}', null],
    ['app.v1.vendors.destroy', 'DELETE', 'api/app/v1/vendors/{vendor}', null],
    ['app.v1.integrations.api-clients.index', 'GET', 'api/app/v1/integrations/api-clients', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.store', 'POST', 'api/app/v1/integrations/api-clients', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.show', 'GET', 'api/app/v1/integrations/api-clients/{client}', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.update', 'PATCH', 'api/app/v1/integrations/api-clients/{client}', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.destroy', 'DELETE', 'api/app/v1/integrations/api-clients/{client}', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.rotate-secret', 'POST', 'api/app/v1/integrations/api-clients/{client}/rotate-secret', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.keys.store', 'POST', 'api/app/v1/integrations/api-clients/{client}/keys', 'integrations.access:api'],
    ['app.v1.integrations.api-clients.keys.destroy', 'DELETE', 'api/app/v1/integrations/api-clients/{client}/keys/{key}', 'integrations.access:api'],
    ['app.v1.integrations.webhook-event-types', 'GET', 'api/app/v1/integrations/webhook-event-types', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.index', 'GET', 'api/app/v1/integrations/webhook-endpoints', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.store', 'POST', 'api/app/v1/integrations/webhook-endpoints', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.show', 'GET', 'api/app/v1/integrations/webhook-endpoints/{endpoint}', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.update', 'PATCH', 'api/app/v1/integrations/webhook-endpoints/{endpoint}', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.destroy', 'DELETE', 'api/app/v1/integrations/webhook-endpoints/{endpoint}', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.test', 'POST', 'api/app/v1/integrations/webhook-endpoints/{endpoint}/test', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.rotate-secret', 'POST', 'api/app/v1/integrations/webhook-endpoints/{endpoint}/rotate-secret', 'integrations.access:api'],
    ['app.v1.integrations.webhook-endpoints.deliveries', 'GET', 'api/app/v1/integrations/webhook-endpoints/{endpoint}/deliveries', 'integrations.access:api'],
    ['app.v1.integrations.webhook-deliveries.redeliver', 'POST', 'api/app/v1/integrations/webhook-deliveries/{delivery}/redeliver', 'integrations.access:api'],
    ['app.v1.integrations.imports.template', 'GET', 'api/app/v1/integrations/imports/templates/{type}', 'integrations.access'],
    ['app.v1.integrations.imports.store', 'POST', 'api/app/v1/integrations/imports', 'integrations.access'],
    ['app.v1.integrations.imports.show', 'GET', 'api/app/v1/integrations/imports/{job}', 'integrations.access'],
    ['app.v1.integrations.exports.store', 'POST', 'api/app/v1/integrations/exports', 'integrations.access'],
    ['app.v1.integrations.exports.show', 'GET', 'api/app/v1/integrations/exports/{job}', 'integrations.access'],
]);

it('registers the API.md §3 public routes with their scope and idempotency', function (string $name, string $method, string $uri, ?string $scope, bool $idempotent) {
    $route = app(Router::class)->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe($uri)
        ->and($route?->methods())->toContain($method)
        ->and(in_array('idempotent', $route?->middleware() ?? [], true))->toBe($idempotent);

    if ($scope !== null) {
        expect($route?->middleware())->toContain('api.scope:'.$scope);
    }
})->with([
    ['public.v1.oauth.token', 'POST', 'api/public/v1/oauth/token', null, false],
    ['public.v1.ping', 'GET', 'api/public/v1/ping', null, false],
    ['public.v1.client', 'GET', 'api/public/v1/client', null, false],
    ['public.v1.openapi', 'GET', 'api/public/v1/openapi.yaml', null, false],
    ['public.v1.vendors.index', 'GET', 'api/public/v1/vendors', 'vendors:read', false],
    ['public.v1.vendors.store', 'POST', 'api/public/v1/vendors', 'vendors:write', true],
    ['public.v1.vendors.show', 'GET', 'api/public/v1/vendors/{vendor}', 'vendors:read', false],
    ['public.v1.vendors.update', 'PATCH', 'api/public/v1/vendors/{vendor}', 'vendors:write', false],
    ['public.v1.vendors.upsert', 'PUT', 'api/public/v1/vendors/external/{system}/{external_id}', 'vendors:write', false],
    ['public.v1.vendors.by-external', 'GET', 'api/public/v1/vendors/external/{system}/{external_id}', 'vendors:read', false],
    ['public.v1.webhook-event-types', 'GET', 'api/public/v1/webhook-event-types', 'webhooks:manage', false],
    ['public.v1.webhook-endpoints.index', 'GET', 'api/public/v1/webhook-endpoints', 'webhooks:manage', false],
    ['public.v1.webhook-endpoints.store', 'POST', 'api/public/v1/webhook-endpoints', 'webhooks:manage', true],
    ['public.v1.webhook-endpoints.show', 'GET', 'api/public/v1/webhook-endpoints/{endpoint}', 'webhooks:manage', false],
    ['public.v1.webhook-endpoints.update', 'PATCH', 'api/public/v1/webhook-endpoints/{endpoint}', 'webhooks:manage', false],
    ['public.v1.webhook-endpoints.destroy', 'DELETE', 'api/public/v1/webhook-endpoints/{endpoint}', 'webhooks:manage', false],
    ['public.v1.webhook-endpoints.test', 'POST', 'api/public/v1/webhook-endpoints/{endpoint}/test', 'webhooks:manage', true],
    ['public.v1.webhook-endpoints.rotate-secret', 'POST', 'api/public/v1/webhook-endpoints/{endpoint}/rotate-secret', 'webhooks:manage', true],
    ['public.v1.webhook-endpoints.deliveries', 'GET', 'api/public/v1/webhook-endpoints/{endpoint}/deliveries', 'webhooks:manage', false],
    ['public.v1.webhook-deliveries.redeliver', 'POST', 'api/public/v1/webhook-deliveries/{delivery}/redeliver', 'webhooks:manage', true],
]);

it('queues the Identity reactions after commit', function () {
    foreach (IntegrationsServiceProvider::IDENTITY_LISTENERS as $event => $listener) {
        expect(Event::getRawListeners()[$event] ?? [])->toContain($listener)
            ->and(is_subclass_of($listener, ShouldQueue::class))->toBeTrue()
            ->and(is_subclass_of($listener, ShouldHandleEventsAfterCommit::class))->toBeTrue();
    }
});

it('links other issuers vendors to an organization whose owner verified the e-mail', function () {
    $supplier = Organization::factory()->create(['cr_number' => '7001234567', 'email' => 'info@supplier.sa']);
    $owner = User::factory()->withMembership($supplier)->create(['email' => 'owner@supplier.sa']);
    $byUserEmail = Vendor::factory()->create(['email' => 'owner@supplier.sa']);
    $byOrgEmail = Vendor::factory()->create(['email' => 'info@supplier.sa']);
    $byCr = Vendor::factory()->create(['cr_number' => '7001234567']);
    $other = Vendor::factory()->create();
    $alreadyLinked = Vendor::factory()->create(['email' => 'owner@supplier.sa', 'linked_organization_id' => Organization::factory()->create()->id]);

    (new LinkVendorsToOrganization)->handle(new EmailVerified($owner));

    expect($byUserEmail->refresh()->linked_organization_id)->toBe($supplier->id)
        ->and($byOrgEmail->refresh()->linked_organization_id)->toBe($supplier->id)
        ->and($byCr->refresh()->linked_organization_id)->toBe($supplier->id)
        ->and($other->refresh()->linked_organization_id)->toBeNull()
        ->and($alreadyLinked->refresh()->linked_organization_id)->not->toBe($supplier->id);
});

it('revokes API access when an organization is deleted', function () {
    $organization = Organization::factory()->apiEnabled()->create();
    $client = ApiClient::factory()->for($organization)->create();
    $key = ApiKey::factory()->for($client)->create();
    $endpoint = WebhookEndpoint::factory()->for($organization)->create();
    $untouched = ApiClient::factory()->create();

    app(RevokeOrganizationApiAccess::class)->handle(new AccountDeleted(1, $organization->id, DeletionScope::User));
    expect($client->refresh()->status)->toBe(ApiClientStatus::Active);

    app(RevokeOrganizationApiAccess::class)->handle(new AccountDeleted(1, $organization->id, DeletionScope::Organization));

    expect($client->refresh()->status)->toBe(ApiClientStatus::Revoked)
        ->and($key->refresh()->revoked_at)->not->toBeNull()
        ->and($endpoint->refresh()->status)->toBe(WebhookEndpointStatus::Disabled)
        ->and($untouched->refresh()->status)->toBe(ApiClientStatus::Active);
});
