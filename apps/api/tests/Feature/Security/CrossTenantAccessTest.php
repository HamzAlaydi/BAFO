<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Support\SampleNotifications;
use App\Support\Auth\CurrentActor;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Bidding\Scenario;
use Tests\Support\Integrations\IntegrationsFixtures;

/*
 * Security review (docs/build/SECURITY_REVIEW.md): the cross-tenant sweep. Every app v1 and
 * public v1 route that takes a resource id is called by a fully privileged outsider (the owner of
 * another organization, or that organization's API client holding every scope) with the ids of
 * organization A's resources. Nothing may answer 2xx or 5xx, and A's data must be untouched.
 *
 * The routes are read from the router, so a new route with an id parameter is covered the day it
 * is added: the test fails until the parameter has a value in securityResourceIds().
 */

/**
 * Routes that take an id but are guest or catalogue endpoints by design.
 */
/**
 * Routes whose id is the caller's own key (an upsert by external id creates or updates the
 * caller's vendor): any status is fine, but organization A's data must stay untouched.
 */
const SECURITY_SWEEP_OWN_KEYED = [
    'public.v1.vendors.upsert',
];

const SECURITY_SWEEP_SKIPPED = [
    'app.v1.lookups.show',
    'app.v1.legal.show',
    'app.v1.integrations.imports.template',
    'app.v1.billing.gateway-webhooks',
    'public.v1.lookups.show',
];

/**
 * Organization A: a live competition with two participants and offers, an award on a second
 * competition, invitations, an attachment, billing, integrations, a team member, a device and a
 * notification of its owner.
 *
 * @return array<string, mixed>
 */
function securityVictim(object $test): array
{
    $rules = ['start_price_minor' => 10_000_000, 'reserve_price_minor' => null, 'min_step_minor' => null, 'min_step_bps' => null];
    $s = Scenario::make($test, bidders: 2, attributes: $rules);
    $s->offer(0, 9_900_000, extra: ['confirm_outlier' => true])->assertCreated();
    $s->offer(1, 9_800_000, extra: ['confirm_outlier' => true])->assertCreated();

    $organization = $s->issuerOrganization;
    $organization->forceFill(['api_enabled' => true])->save();
    $owner = $s->issuer;
    $competition = $s->refresh();

    $invitation = Invitation::query()->where('competition_id', $competition->id)->orderBy('id')->firstOrFail();
    $attachment = CompetitionAttachment::factory()->create(['competition_id' => $competition->id]);

    $awarded = Scenario::make($test, bidders: 1, attributes: $rules);
    $awarded->offer(0, 9_000_000, extra: ['confirm_outlier' => true])->assertCreated();
    $award = Award::factory()->create(['offer_id' => $awarded->lastOffer()?->id]);
    $award->competition()->update(['organization_id' => $organization->id]);

    $payment = Payment::factory()->succeeded()->create(['organization_id' => $organization->id, 'created_by_user_id' => $owner->id]);
    $invoice = Invoice::factory()->create(['payment_id' => $payment->id]);
    $invoiceFile = File::factory()->purpose(FilePurpose::InvoicePdf)->create(['organization_id' => $organization->id]);
    Storage::disk('private')->put($invoiceFile->path, '%PDF-1.4 invoice');

    $vendor = Vendor::factory()->create(['organization_id' => $organization->id]);
    $externalRef = ExternalRef::factory()->create([
        'organization_id' => $organization->id,
        'refable_type' => 'vendor',
        'refable_id' => $vendor->id,
    ]);
    $client = ApiClient::factory()->create(['organization_id' => $organization->id]);
    $key = ApiKey::factory()->create(['api_client_id' => $client->id]);
    $event = WebhookEvent::factory()->create(['organization_id' => $organization->id]);
    $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $organization->id]);
    $delivery = WebhookDelivery::factory()->create(['webhook_event_id' => $event->id, 'webhook_endpoint_id' => $endpoint->id]);
    $import = ImportJob::factory()->create(['organization_id' => $organization->id, 'created_by_user_id' => $owner->id]);
    $export = ExportJob::factory()->create(['organization_id' => $organization->id, 'created_by_user_id' => $owner->id]);

    $member = User::factory()->withMembership($organization, OrgRole::Member)->create();
    $membership = Membership::query()->where('user_id', $member->id)->firstOrFail();
    $device = DeviceToken::factory()->create(['user_id' => $owner->id]);

    $class = NotificationType::CompetitionOpened->notificationClass();
    $notification = $class::fromPayload(SampleNotifications::payload(NotificationType::CompetitionOpened));
    $owner->notifyNow($notification, ['database']);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'competition' => $competition,
        'ids' => [
            'competition' => $competition->public_id,
            'invitation' => $invitation->public_id,
            'attachment' => $attachment->public_id,
            'award' => $award->public_id,
            'payment' => $payment->public_id,
            'invoice' => $invoice->public_id,
            'file' => $invoiceFile->public_id,
            'vendor' => $vendor->public_id,
            'system' => $externalRef->system,
            'external_id' => $externalRef->value,
            'client' => $client->public_id,
            'key' => $key->public_id,
            'endpoint' => $endpoint->public_id,
            'delivery' => $delivery->public_id,
            'job' => null, // imports/{job} and exports/{job}: see securityJobId()
            'membership' => $membership->public_id,
            'device' => $device->public_id,
            'notification' => (string) $notification->id,
            'import' => $import->public_id,
            'export' => $export->public_id,
        ],
    ];
}

/**
 * A fingerprint of organization A's data: any write by the outsider changes it.
 *
 * @param  array<string, mixed>  $victim
 */
function securityFingerprint(array $victim): string
{
    /** @var Organization $organization */
    $organization = $victim['organization'];
    $id = $organization->id;
    $competitionIds = Competition::query()->where('organization_id', $id)->pluck('id');

    return json_encode([
        Competition::query()->withTrashed()->where('organization_id', $id)->orderBy('id')->get(['id', 'title', 'status', 'effective_close_at', 'deleted_at'])->toArray(),
        Invitation::query()->whereIn('competition_id', $competitionIds)->orderBy('id')->get(['id', 'status', 'email', 'organization_id', 'sent_at'])->toArray(),
        CompetitionAttachment::query()->whereIn('competition_id', $competitionIds)->orderBy('id')->get(['id', 'title', 'sort_order'])->toArray(),
        Offer::query()->whereIn('competition_id', $competitionIds)->count(),
        Award::query()->whereIn('competition_id', $competitionIds)->orderBy('id')->get(['id', 'status', 'erp_sync_status'])->toArray(),
        Payment::query()->where('organization_id', $id)->orderBy('id')->get(['id', 'status'])->toArray(),
        Vendor::query()->where('organization_id', $id)->orderBy('id')->get(['id', 'name', 'status', 'updated_at'])->toArray(),
        ApiClient::query()->where('organization_id', $id)->orderBy('id')->get(['id', 'name', 'status', 'scopes', 'revoked_at'])->toArray(),
        ApiKey::query()->whereIn('api_client_id', ApiClient::query()->select('id')->where('organization_id', $id))->orderBy('id')->get(['id', 'revoked_at'])->toArray(),
        WebhookEndpoint::query()->withTrashed()->where('organization_id', $id)->orderBy('id')->get(['id', 'url', 'status', 'deleted_at'])->toArray(),
        WebhookEndpoint::query()->withTrashed()->where('organization_id', $id)->orderBy('id')->get()->map(static fn (WebhookEndpoint $e): string => hash('sha256', (string) $e->secret))->all(),
        WebhookDelivery::query()->orderBy('id')->get(['id', 'status', 'attempts'])->toArray(),
        Membership::query()->where('organization_id', $id)->orderBy('id')->get(['id', 'role', 'status', 'can_award', 'can_purchase'])->toArray(),
        DeviceToken::query()->where('user_id', $victim['owner']->id)->orderBy('id')->get(['id', 'user_id'])->toArray(),
        DatabaseNotification::query()->where('notifiable_id', $victim['owner']->id)->orderBy('id')->get(['id', 'read_at'])->toArray(),
    ], JSON_THROW_ON_ERROR);
}

/**
 * Fills the route's parameters with organization A's ids.
 *
 * @param  array<string, mixed>  $victim
 */
function securitySweepUri(Route $route, array $victim): ?string
{
    /** @var array<string, string|null> $ids */
    $ids = $victim['ids'];
    $uri = '/'.$route->uri();

    foreach ($route->parameterNames() as $name) {
        $value = match (true) {
            $name === 'job' && str_contains($uri, '/imports/') => $ids['import'],
            $name === 'job' && str_contains($uri, '/exports/') => $ids['export'],
            default => $ids[$name] ?? null,
        };

        if ($value === null) {
            return null;
        }

        $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], rawurlencode($value), $uri);
    }

    return $uri;
}

/**
 * A plausible body for every write: many fields of many resources, so that validation passes
 * wherever it runs before authorization and the answer shows the authorization decision.
 *
 * @return array<string, mixed>
 */
function securitySweepBody(string $routeName): array
{
    $reasonKind = str_ends_with($routeName, '.cancel') ? CloseReasonKind::Cancel : CloseReasonKind::NotAwarded;

    return [
        'close_reason_id' => CloseReason::factory()->kind($reasonKind)->create()->public_id,
        'note' => 'Pwned',
        'title' => 'Pwned',
        'name' => 'Pwned',
        'description' => 'Pwned',
        'url' => 'https://erp.example.sa/hook',
        'event_types' => ['competition.published'],
        'scopes' => ['competitions:read'],
        'role' => 'admin',
        'can_award' => true,
        'can_purchase' => true,
        'status' => str_ends_with($routeName, 'erp-sync') ? 'synced' : 'active',
        'reason' => 'Pwned',
        'reason_code' => 'other',
        'minutes' => 30,
        'new_close_at' => now()->addDays(3)->toIso8601ZuluString(),
        'body' => 'Pwned',
        'amount_minor' => 9_700_000,
        'participant_id' => '01j00000000000000000000000',
        'participant_ids' => ['01j00000000000000000000000'],
        'duration_minutes' => 30,
        'accept_terms' => true,
        'invitations' => [['email' => 'pwned@example.test']],
        'emails' => ['pwned@example.test'],
        'mode' => 'none',
        'return_url' => 'http://localhost:3000/ar/dashboard',
        'erp_status' => 'synced',
        'email' => 'pwned@example.test',
        'erp_reference' => 'PO-1',
        'expires_in_days' => 30,
        'sort_order' => 3,
        'kind' => 'external_link',
    ];
}

/**
 * @return list<Route>
 */
function securitySweepRoutes(string $prefix): array
{
    // Own-keyed writes run last, so that the reads before them look for A's keys only.
    $routes = array_values(array_filter(
        RouteFacade::getRoutes()->getRoutes(),
        static fn (Route $route): bool => str_starts_with((string) $route->getName(), $prefix)
            && $route->parameterNames() !== []
            && ! in_array($route->getName(), SECURITY_SWEEP_SKIPPED, true),
    ));

    usort($routes, static fn (Route $a, Route $b): int => (int) in_array($a->getName(), SECURITY_SWEEP_OWN_KEYED, true)
        <=> (int) in_array($b->getName(), SECURITY_SWEEP_OWN_KEYED, true));

    return $routes;
}

beforeEach(function () {
    Queue::fake();
    Http::fake();
});

it('refuses every app v1 resource route of another organization to its owner', function () {
    $victim = securityVictim($this);
    $before = securityFingerprint($victim);

    $outsiderOrganization = Organization::factory()->apiEnabled()->auctionEnabled()->create();
    $outsider = User::factory()->withMembership($outsiderOrganization, OrgRole::Owner)->create();

    $checked = 0;
    $failures = [];

    foreach (securitySweepRoutes('app.v1.') as $route) {
        $uri = securitySweepUri($route, $victim);
        expect($uri)->not->toBeNull("No id for a parameter of [{$route->getName()}]: add it to securityVictim().");

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            Auth::forgetGuards();
            CurrentActor::clear();
            Sanctum::actingAs($outsider);

            $response = $this->json($method, (string) $uri, securitySweepBody((string) $route->getName()), ['Idempotency-Key' => 'sweep-'.bin2hex(random_bytes(6))]);

            if (! in_array($response->status(), [403, 404], true) && ! in_array($route->getName(), SECURITY_SWEEP_OWN_KEYED, true)) {
                $failures[] = "{$method} {$route->getName()} answered {$response->status()}: ".$response->getContent();
            }

            $checked++;
        }
    }

    expect($failures)->toBe([])
        ->and($checked)->toBeGreaterThan(50)
        ->and(securityFingerprint($victim))->toBe($before);
});

it('refuses every public v1 resource route of another organization to its all-scopes API client', function () {
    $victim = securityVictim($this);
    $before = securityFingerprint($victim);

    $outsiderOrganization = Organization::factory()->apiEnabled()->create();
    [$plainKey] = IntegrationsFixtures::apiKey(scopes: ApiScope::cases(), organization: $outsiderOrganization);

    $checked = 0;
    $failures = [];

    foreach (securitySweepRoutes('public.v1.') as $route) {
        $uri = securitySweepUri($route, $victim);
        expect($uri)->not->toBeNull("No id for a parameter of [{$route->getName()}]: add it to securityVictim().");

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            Auth::forgetGuards();
            CurrentActor::clear();

            $response = $this->json($method, (string) $uri, securitySweepBody((string) $route->getName()), [
                'Authorization' => 'Bearer '.$plainKey,
                'Idempotency-Key' => 'sweep-'.bin2hex(random_bytes(6)),
            ]);

            if (! in_array($response->status(), [403, 404], true) && ! in_array($route->getName(), SECURITY_SWEEP_OWN_KEYED, true)) {
                $failures[] = "{$method} {$route->getName()} answered {$response->status()}: ".$response->getContent();
            }

            $checked++;
        }
    }

    expect($failures)->toBe([])
        ->and($checked)->toBeGreaterThan(25)
        ->and(securityFingerprint($victim))->toBe($before);
});

/*
 * Inside the organization: a plain member (no can_award, no can_purchase, not the creator of the
 * competition) against every route that needs competitions.manage, competitions.award,
 * billing.view / billing.purchase, organization.update, team.manage or integrations.manage.
 */
it('refuses a plain member every issuer, billing, team and integrations write of its own organization', function (string $routeName) {
    $victim = securityVictim($this);
    $before = securityFingerprint($victim);

    $member = User::query()
        ->whereKey(Membership::query()->where('public_id', $victim['ids']['membership'])->value('user_id'))
        ->firstOrFail();

    $route = RouteFacade::getRoutes()->getByName($routeName);
    expect($route)->not->toBeNull();

    $uri = securitySweepUri($route, $victim);
    $method = array_values(array_diff($route->methods(), ['HEAD']))[0];

    Auth::forgetGuards();
    CurrentActor::clear();
    Sanctum::actingAs($member);

    $response = $this->json($method, (string) $uri, securitySweepBody($routeName), ['Idempotency-Key' => 'role-'.bin2hex(random_bytes(6))]);

    expect($response->status())->toBeIn([403, 404], "{$method} {$routeName} answered {$response->status()}: ".$response->getContent())
        ->and(securityFingerprint($victim))->toBe($before);
})->with([
    'app.v1.competitions.update', 'app.v1.competitions.destroy', 'app.v1.competitions.publish',
    'app.v1.competitions.extend', 'app.v1.competitions.cancel', 'app.v1.competitions.close',
    'app.v1.competitions.suggestions',
    'app.v1.competitions.invitations.store', 'app.v1.competitions.invitations.update',
    'app.v1.competitions.invitations.destroy', 'app.v1.competitions.invitations.resend',
    'app.v1.competitions.attachments.store', 'app.v1.competitions.attachments.update', 'app.v1.competitions.attachments.destroy',
    'app.v1.competitions.bafo-round.store', 'app.v1.competitions.award.store', 'app.v1.competitions.award.revoke',
    'app.v1.competitions.sponsorship.update', 'app.v1.competitions.sponsorship.checkout',
    'app.v1.billing.checkout.subscription', 'app.v1.billing.coupons.validate', 'app.v1.billing.trial',
    'app.v1.billing.subscription', 'app.v1.billing.invoices.index', 'app.v1.billing.invoices.show',
    'app.v1.billing.invoices.pdf', 'app.v1.billing.payments.show', 'app.v1.billing.payments.verify',
    'app.v1.billing.vouchers.index', 'app.v1.files.download',
    'app.v1.organization.update', 'app.v1.organization.logo.destroy', 'app.v1.organization.profile-document.destroy',
    'app.v1.team.members.index', 'app.v1.team.members.store', 'app.v1.team.members.update',
    'app.v1.team.members.destroy', 'app.v1.team.members.resend',
    'app.v1.integrations.api-clients.index', 'app.v1.integrations.api-clients.store', 'app.v1.integrations.api-clients.show',
    'app.v1.integrations.api-clients.update', 'app.v1.integrations.api-clients.destroy',
    'app.v1.integrations.api-clients.keys.store', 'app.v1.integrations.api-clients.keys.destroy',
    'app.v1.integrations.api-clients.rotate-secret',
    'app.v1.integrations.webhook-endpoints.index', 'app.v1.integrations.webhook-endpoints.store',
    'app.v1.integrations.webhook-endpoints.update', 'app.v1.integrations.webhook-endpoints.destroy',
    'app.v1.integrations.webhook-endpoints.test', 'app.v1.integrations.webhook-endpoints.rotate-secret',
    'app.v1.integrations.webhook-deliveries.redeliver',
    'app.v1.integrations.imports.store', 'app.v1.integrations.imports.show',
    'app.v1.integrations.exports.store', 'app.v1.integrations.exports.show',
]);
