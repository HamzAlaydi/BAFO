<?php

declare(strict_types=1);

use App\Modules\Admin\Models\Admin;
use App\Modules\Billing\Database\Seeders\BillingReferenceSeeder;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Database\Seeders\CatalogReferenceSeeder;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Actions\UpdateOrganizationFeatures;
use App\Modules\Identity\Models\Organization;
use App\Modules\Platform\Database\Seeders\PlatformReferenceSeeder;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Integrations\IntegrationsFixtures;
use Tests\Support\Integrations\PassportKeys;

/*
|--------------------------------------------------------------------------
| End-to-end HTTP scenarios (QA)
|--------------------------------------------------------------------------
|
| Every step goes through the public HTTP surface, as the web app, the mobile app, the hosted
| fake checkout page and an ERP would call it. Only three things happen outside HTTP: the
| platform admin switches the organization flags (a Filament action, through the Identity
| Action), the clock is moved with travelTo(), and the scheduler command competitions:tick runs.
| Queues are synchronous (phpunit.xml), so listeners, the close job, the report job, invoices
| and webhook deliveries run inline.
|
| AUCTION (forward): register + OTP + login, subscription through the fake checkout, R4
| sponsorship paid before publish, participant A joins on a sponsored pass, participant B buys
| a plan and joins, live bidding (opening price, absolute min step, must beat own, anti-sniping,
| the close race), no price leak with show_prices = false, close, award, result PDF, invoices,
| signed webhooks, and the award read by an ERP with OAuth2 client credentials.
|
| TENDER (reverse): the same core path, with a bps step, an initial phase and a final window
| where offers must beat the leading offer, prices and ranks shown, a trial participant, and
| the winning amount published.
|
*/

const E2E_RETURN_URL = 'http://localhost:3000/ar/dashboard/billing/checkout/return';
const E2E_WEBHOOK_URL = 'https://erp.issuer-e2e.example/bafo/webhooks';
const E2E_PASSWORD = 'E2e-Passw0rd!';

beforeEach(function () {
    $this->seed([PlatformReferenceSeeder::class, CatalogReferenceSeeder::class, BillingReferenceSeeder::class]);

    Mail::fake();
    PassportKeys::load();

    // Webhooks go to a public-looking ERP host; nothing may reach the network.
    config(['bafo.integrations.webhooks.allow_private_targets' => false]);
    IntegrationsFixtures::fakeDns()->map('erp.issuer-e2e.example', ['203.0.113.10']);
    Http::preventStrayRequests();
    Http::fake(['https://erp.issuer-e2e.example/*' => Http::response('', 204)]);

    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00:00'));
});

/**
 * Headers of a first-party client signed in with `$token` (the Sanctum guard caches the first
 * user of a test, so it is reset before every switch of identity).
 *
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function e2eHeaders(?string $token, array $extra = []): array
{
    Auth::forgetGuards();
    CurrentActor::clear();

    return [
        'X-Platform' => 'web',
        'Accept-Language' => 'en',
        ...($token !== null ? ['Authorization' => 'Bearer '.$token] : []),
        ...$extra,
    ];
}

/**
 * @param  array<string, mixed>  $body
 */
function e2ePost(string $uri, array $body, ?string $token, array $extra = []): TestResponse
{
    return test()->postJson($uri, $body, e2eHeaders($token, $extra));
}

function e2eGet(string $uri, ?string $token): TestResponse
{
    return test()->getJson($uri, e2eHeaders($token));
}

/**
 * POST /auth/register → POST /auth/otp/verify (OTP_FAKE_CODE) → POST /auth/login.
 *
 * @return array{token: string, email: string, organization: Organization, name: string}
 */
function e2eRegister(string $key, string $crNumber, string $vatNumber): array
{
    $email = "{$key}@e2e-{$key}.sa";
    $name = 'شركة '.$key.' للاختبار';

    e2ePost('/api/app/v1/auth/register', [
        'name' => 'مالك '.$key,
        'email' => $email,
        'phone' => '+9665'.substr($crNumber, -8),
        'password' => E2E_PASSWORD,
        'password_confirmation' => E2E_PASSWORD,
        'locale' => 'en',
        'organization' => [
            'name' => $name,
            'cr_number' => $crNumber,
            'region_id' => Region::query()->where('code', 'RIY')->value('public_id'),
            'city' => 'الرياض',
            'vat_registered' => true,
            'vat_number' => $vatNumber,
            'legal_name_ar' => $name,
            'legal_name_en' => ucfirst($key).' Trading Co.',
            'national_address' => [
                'building_number' => '1234',
                'street' => 'طريق الملك فهد',
                'district' => 'العليا',
                'postal_code' => '12345',
                'additional_number' => '5678',
                'short_address' => 'RRRD2929',
            ],
            'category_ids' => [Category::query()->where('code', 'it_hardware')->value('public_id')],
            'visible_in_suggestions' => true,
        ],
        'accept_terms' => true,
        'accept_privacy' => true,
    ], null)
        ->assertCreated()
        ->assertJsonPath('data.email', $email)
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonMissingPath('data.token');

    // Signing in before the e-mail is verified is refused (and a fresh code is sent).
    e2ePost('/api/app/v1/auth/login', ['email' => $email, 'password' => E2E_PASSWORD, 'device_name' => 'e2e'], null)
        ->assertForbidden()
        ->assertJsonPath('code', 'email_not_verified');

    test()->travel(61)->seconds(); // the OTP resend cooldown

    e2ePost('/api/app/v1/auth/otp/verify', [
        'email' => $email, 'code' => '123456', 'purpose' => 'email_verification', 'device_name' => 'e2e',
    ], null)
        ->assertOk()
        ->assertJsonPath('data.user.status', 'active')
        ->assertJsonPath('data.membership.role', 'owner')
        ->assertJsonPath('data.token_type', 'Bearer');

    e2ePost('/api/app/v1/auth/login', ['email' => $email, 'password' => 'wrong-password', 'device_name' => 'e2e'], null)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_credentials');

    $token = (string) e2ePost('/api/app/v1/auth/login', ['email' => $email, 'password' => E2E_PASSWORD, 'device_name' => 'e2e'], null)
        ->assertOk()
        ->assertJsonPath('data.user.email', $email)
        ->assertJsonPath('data.entitlements.can_issue', false)
        ->json('data.token');

    e2eGet('/api/app/v1/me', $token)->assertOk()->assertJsonPath('data.organization.name', $name);

    return [
        'token' => $token,
        'email' => $email,
        'name' => $name,
        'organization' => Organization::query()->where('cr_number', $crNumber)->sole(),
    ];
}

/**
 * The platform admin switches organization flags (Filament → Identity Action, admin actor).
 *
 * @param  array<string, bool>  $features
 */
function e2eAdminFeatures(Organization $organization, array $features): void
{
    $admin = Admin::query()->first() ?? Admin::factory()->superAdmin()->create();

    app(UpdateOrganizationFeatures::class)->handle($organization, $features, Actor::forAdmin($admin));
}

/**
 * Pays a pending payment on the hosted fake checkout page, then polls and verifies it as the
 * web app does (CONVENTIONS §7). Returns the payment resource.
 *
 * @return array<string, mixed>
 */
function e2ePayOnFakePage(string $paymentId, string $redirectUrl, string $token): array
{
    expect($redirectUrl)->toEndWith('/pay/fake/'.$paymentId);

    test()->get($redirectUrl)->assertOk()->assertSee($paymentId, false);
    test()->post('/pay/fake/'.$paymentId.'/approve')
        ->assertStatus(303)
        ->assertRedirect(E2E_RETURN_URL.'?payment='.$paymentId);

    e2eGet('/api/app/v1/billing/payments/'.$paymentId, $token)->assertOk()->assertJsonPath('data.status', 'succeeded');

    return e2ePost('/api/app/v1/billing/payments/'.$paymentId.'/verify', [], $token)
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded')
        ->json('data');
}

/**
 * POST /billing/checkout/subscription on the fake gateway, approved on the hosted page.
 *
 * @return array<string, mixed> the payment
 */
function e2eSubscribe(string $token, string $planCode): array
{
    $checkout = e2ePost('/api/app/v1/billing/checkout/subscription', [
        'plan_id' => Plan::query()->where('code', $planCode)->value('public_id'),
        'interval' => 'monthly',
        'return_url' => E2E_RETURN_URL,
    ], $token, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.purpose', 'subscription')
        ->assertJsonPath('data.status', 'pending');

    $payment = e2ePayOnFakePage((string) $checkout->json('data.id'), (string) $checkout->json('data.redirect_url'), $token);

    e2eGet('/api/app/v1/billing/subscription', $token)
        ->assertOk()
        ->assertJsonPath('data.current.plan.code', $planCode)
        ->assertJsonPath('data.current.status', 'active');

    return $payment;
}

/**
 * The competition the invitee sees in its participating list, with its invitation.
 *
 * @return array<string, mixed>
 */
function e2eInvitation(string $competitionId, string $token): array
{
    $item = collect(e2eGet('/api/app/v1/competitions?role=participant', $token)->assertOk()->json('data'))
        ->firstWhere('id', $competitionId);

    expect($item)->not->toBeNull()
        ->and($item['invitation']['id'] ?? null)->toBeString();

    return $item;
}

function e2eOffer(string $competitionId, string $token, int $amount): TestResponse
{
    return e2ePost("/api/app/v1/competitions/{$competitionId}/offers", ['amount_minor' => $amount], $token, [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

/**
 * Asserts that a participant-facing payload leaks nothing of the other participant: no
 * identity and none of the given amounts (ARCHITECTURE §7.9, CONVENTIONS §2.5 leak tests).
 *
 * @param  array<string, mixed>|null  $other  e2eRegister() result of the other participant
 * @param  list<int>  $hiddenAmounts
 */
function e2eAssertNoLeak(TestResponse $response, ?array $other, array $hiddenAmounts): void
{
    $json = (string) $response->getContent();

    if ($other !== null) {
        expect($json)->not->toContain($other['name'])
            ->and($json)->not->toContain($other['organization']->public_id)
            ->and($json)->not->toContain($other['email']);
    }

    foreach ($hiddenAmounts as $amount) {
        expect($json)->not->toMatch('/(?<![0-9])'.$amount.'(?![0-9])/');
    }
}

/**
 * Every webhook request the fake ERP received carries a Standard Webhooks signature that the
 * API.md §4.3 consumer check accepts with the endpoint secret. Returns the event types seen.
 *
 * @return list<string>
 */
function e2eVerifiedWebhookTypes(string $secret): array
{
    $types = [];

    Http::assertSent(function (HttpRequest $request) use ($secret, &$types): bool {
        if ($request->url() !== E2E_WEBHOOK_URL) {
            return false;
        }

        $body = $request->body();
        $id = $request->header('webhook-id')[0] ?? '';
        $timestamp = $request->header('webhook-timestamp')[0] ?? '';
        $signatures = $request->header('webhook-signature')[0] ?? '';

        $key = base64_decode(substr($secret, 6), true);
        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) $key, true));
        $valid = collect(explode(' ', $signatures))
            ->contains(fn (string $s): bool => str_starts_with($s, 'v1,') && hash_equals($expected, substr($s, 3)));

        // The 5-minute tolerance of §4.3 is the consumer's check at receipt time; the clock of
        // this test has moved on since, so only the order is checked here.
        expect($valid)->toBeTrue()
            ->and((int) $timestamp)->toBeGreaterThan(0)->toBeLessThanOrEqual(now()->getTimestamp());

        $payload = json_decode($body, true);
        $types[] = (string) ($payload['type'] ?? '');

        return true;
    });

    return $types;
}

/**
 * The webhook payload of the given type, as the ERP received it.
 *
 * @return array<string, mixed>
 */
function e2eWebhookPayload(string $type): array
{
    $request = Http::recorded(fn (HttpRequest $r): bool => $r->url() === E2E_WEBHOOK_URL
        && (json_decode($r->body(), true)['type'] ?? null) === $type)->first()[0] ?? null;

    expect($request)->not->toBeNull();

    return json_decode($request->body(), true);
}

/**
 * The issuer's ERP: an API client created in the dashboard (secret shown once), then an
 * OAuth2 client-credentials token from the public API.
 */
function e2eErpAccessToken(string $issuerToken): string
{
    $client = e2ePost('/api/app/v1/integrations/api-clients', [
        'name' => 'SAP S/4 (E2E)',
        'scopes' => ['awards:read', 'competitions:read'],
    ], $issuerToken)->assertCreated();

    Auth::forgetGuards();
    CurrentActor::clear();

    $token = test()->postJson('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->json('data.client_id'),
        'client_secret' => $client->json('data.client_secret'),
        'scope' => 'awards:read',
    ])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('scope', 'awards:read')
        ->json('access_token');

    expect($token)->toBeString()->not->toBeEmpty();

    return (string) $token;
}

it('runs an auction from registration to an award read by the ERP', function () {
    // 1. The issuer registers, verifies its e-mail and signs in; the admin enables auctions,
    //    sponsorship and the API; the issuer subscribes on the fake checkout.
    $issuer = e2eRegister('issuer', '1010900001', '300000000000003');
    e2eAdminFeatures($issuer['organization'], ['auction_enabled' => true, 'sponsorship_enabled' => true, 'api_enabled' => true]);

    $body = [
        'title' => 'بيع معدات حاسب مستعملة',
        'description' => 'مزايدة على معدات الحاسب الفائضة.',
        'category_id' => Category::query()->where('code', 'it_hardware')->value('public_id'),
        'region_id' => Region::query()->where('code', 'RIY')->value('public_id'),
        'direction' => 'auction',
        'format' => 'live',
        'rules' => [
            'start_price_minor' => 1_000_000,
            'reserve_price_minor' => null,
            'min_step_minor' => 10_000,
            'min_step_bps' => null,
            'amount_granularity_minor' => 100,
            'must_beat' => 'own',
            'rank_visibility' => 'leading_flag',
            'show_prices' => false,
            'auto_extend' => ['enabled' => true, 'window_seconds' => 120, 'by_seconds' => 120, 'max_extensions' => 3],
            'final_window_minutes' => null,
            'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
            'min_participants' => 2,
            'result_publication' => 'outcome_only',
        ],
        'bidding_opens_at' => '2026-10-06T07:00:00Z',
        'scheduled_close_at' => '2026-10-06T09:00:00Z',
    ];

    e2ePost('/api/app/v1/competitions', $body, $issuer['token'])
        ->assertForbidden()
        ->assertJsonPath('code', 'issuer_plan_required');

    $subscriptionPayment = e2eSubscribe($issuer['token'], 'pro');
    e2eGet('/api/app/v1/me', $issuer['token'])->assertOk()->assertJsonPath('data.entitlements.can_issue', true);

    // The ERP receives every event type.
    $endpoint = e2ePost('/api/app/v1/integrations/webhook-endpoints', [
        'url' => E2E_WEBHOOK_URL, 'event_types' => ['*'], 'description' => 'ERP gateway',
    ], $issuer['token'])->assertCreated();
    $secret = (string) $endpoint->json('data.secret');
    expect($secret)->toStartWith('whsec_');

    // 2. Two suppliers register: A has no plan, B will buy one.
    $supplierA = e2eRegister('supplier-a', '1010900002', '300000000100003');
    $supplierB = e2eRegister('supplier-b', '1010900003', '300000000200003');

    // 3. Draft auction, sponsorship for selected invitees, two invitations (A sponsored).
    $competitionId = (string) e2ePost('/api/app/v1/competitions', $body, $issuer['token'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.direction', 'auction')
        ->json('data.id');
    $base = "/api/app/v1/competitions/{$competitionId}";

    test()->putJson("{$base}/sponsorship", ['mode' => 'selected'], e2eHeaders($issuer['token']))->assertOk();

    e2ePost("{$base}/invitations", ['invitations' => [
        ['email' => $supplierA['email'], 'name' => 'Supplier A', 'sponsored' => true],
        ['email' => $supplierB['email'], 'name' => 'Supplier B', 'sponsored' => false],
    ]], $issuer['token'])->assertCreated()->assertJsonCount(2, 'data');

    e2eGet("{$base}/sponsorship/quote", $issuer['token'])->assertOk()->assertJsonPath('data.passes_to_buy', 1);

    // Publishing before the pass is paid is refused with the quote.
    e2ePost("{$base}/publish", [], $issuer['token'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'sponsorship_payment_required')
        ->assertJsonPath('details.quote.passes_to_buy', 1);

    // 4. The issuer pays the pass fee; the payment publishes the competition.
    $checkout = e2ePost("{$base}/sponsorship/checkout", ['intent' => 'publish', 'return_url' => E2E_RETURN_URL], $issuer['token'], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('data.purpose', 'sponsorship');
    $sponsorshipPayment = e2ePayOnFakePage((string) $checkout->json('data.id'), (string) $checkout->json('data.redirect_url'), $issuer['token']);

    e2eGet($base, $issuer['token'])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.viewer_role', 'issuer');

    // 5. A (no plan) joins on the sponsored pass.
    $invitationA = e2eInvitation($competitionId, $supplierA['token']);
    expect($invitationA['access']['coverage'])->toBe('sponsored');

    e2eGet($base, $supplierA['token'])
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'invitee')
        ->assertJsonPath('data.access.coverage', 'sponsored')
        ->assertJsonPath('data.access.state', 'join_required');

    e2ePost('/api/app/v1/invitations/'.$invitationA['invitation']['id'].'/join', ['accept_terms' => true], $supplierA['token'])->assertOk();

    // 6. B has no plan: joining is refused until B subscribes.
    $invitationB = e2eInvitation($competitionId, $supplierB['token']);
    e2ePost('/api/app/v1/invitations/'.$invitationB['invitation']['id'].'/join', ['accept_terms' => true], $supplierB['token'])
        ->assertForbidden()
        ->assertJsonPath('code', 'plan_required');

    e2eSubscribe($supplierB['token'], 'single');
    e2ePost('/api/app/v1/invitations/'.$invitationB['invitation']['id'].'/join', ['accept_terms' => true], $supplierB['token'])->assertOk();

    expect(Participant::query()->where('organization_id', $supplierA['organization']->id)->sole()->entitlement_source)->toBe(EntitlementSource::SponsoredPass)
        ->and(Participant::query()->where('organization_id', $supplierB['organization']->id)->sole()->entitlement_source)->toBe(EntitlementSource::Plan);

    // 7. The scheduler opens bidding.
    e2eOffer($competitionId, $supplierA['token'], 1_000_000)->assertStatus(409)->assertJsonPath('code', 'offer_not_accepting');

    test()->travelTo(CarbonImmutable::parse('2026-10-06 07:00:01'));
    test()->artisan('competitions:tick')->assertSuccessful();
    e2eGet($base, $issuer['token'])->assertOk()->assertJsonPath('data.status', 'live');

    // 8. Live bidding (forward: the highest offer leads).
    // Every attempt, accepted or not, counts against the 1-per-2-s limit of the participant.
    e2eOffer($competitionId, $supplierA['token'], 900_000)->assertUnprocessable()->assertJsonPath('code', 'offer_start_price');
    e2eOffer($competitionId, $supplierA['token'], 1_000_050)->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierA['token'], 1_000_050)->assertUnprocessable()->assertJsonPath('code', 'offer_granularity');
    test()->travel(3)->seconds();

    e2eOffer($competitionId, $supplierA['token'], 1_000_500)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', true)
        ->assertJsonPath('data.live.leading_amount_minor', null);

    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierB['token'], 1_050_000)->assertCreated()->assertJsonPath('data.live.is_leading', true);

    // Must beat its own offer by the minimum step (100 SAR).
    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierA['token'], 1_005_000)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'offer_step_not_met')
        ->assertJsonPath('details.required_amount_minor', 1_010_500);

    test()->travel(3)->seconds();
    $improved = e2eOffer($competitionId, $supplierA['token'], 1_020_000)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', false)
        ->assertJsonPath('data.live.rank', null)
        ->assertJsonPath('data.live.ladder', null)
        ->assertJsonPath('data.live.leading_amount_minor', null)
        ->assertJsonPath('data.live.required_next_amount_minor', 1_030_000);

    // show_prices = false: nothing of B reaches A, and nothing of A reaches B.
    e2eAssertNoLeak($improved, $supplierB, [1_050_000]);
    e2eAssertNoLeak(e2eGet("{$base}/live", $supplierA['token'])->assertOk()->assertJsonPath('data.is_leading', false), $supplierB, [1_050_000]);
    e2eAssertNoLeak(e2eGet($base, $supplierA['token'])->assertOk(), $supplierB, [1_050_000]);
    e2eAssertNoLeak(e2eGet('/api/app/v1/competitions?role=participant', $supplierA['token'])->assertOk(), $supplierB, [1_050_000]);
    e2eAssertNoLeak(e2eGet("{$base}/my-offers", $supplierA['token'])->assertOk(), $supplierB, [1_050_000]);
    e2eAssertNoLeak(e2eGet("{$base}/live", $supplierB['token'])->assertOk()->assertJsonPath('data.is_leading', true), $supplierA, [1_000_500, 1_020_000]);
    e2eGet("{$base}/offers", $supplierA['token'])->assertForbidden();

    // 9. Anti-sniping: A takes the lead in the last minute and the close moves by 120 s.
    $close = CarbonImmutable::parse('2026-10-06 09:00:00');
    test()->travelTo($close->subSeconds(30));

    $snipe = e2eOffer($competitionId, $supplierA['token'], 1_060_000)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', true)
        ->assertJsonPath('data.live.extension_count', 1)
        ->assertJsonPath('data.live.effective_close_at', '2026-10-06T09:01:30.000Z');
    e2eAssertNoLeak($snipe, $supplierB, [1_050_000]);

    $issuerLive = e2eGet("{$base}/live", $issuer['token'])
        ->assertOk()
        ->assertJsonPath('data.leader.amount_minor', 1_060_000)
        ->assertJsonPath('data.leader.organization.id', $supplierA['organization']->public_id)
        ->assertJsonPath('data.extension_count', 1);
    $winnerParticipantId = (string) $issuerLive->json('data.leader.participant_id');

    // The old close has passed but the extended one has not: offers are still accepted. B ties
    // A's amount; the earlier offer keeps the lead, so the close does not move again.
    test()->travelTo($close->addSeconds(10));
    e2eOffer($competitionId, $supplierB['token'], 1_060_000)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', false)
        ->assertJsonPath('data.live.extension_count', 1)
        ->assertJsonPath('data.live.effective_close_at', '2026-10-06T09:01:30.000Z');

    // The close race: at the extended close the status is still live, but offers are refused.
    test()->travelTo(CarbonImmutable::parse('2026-10-06 09:01:30'));
    e2eOffer($competitionId, $supplierB['token'], 1_100_000)->assertStatus(409)->assertJsonPath('code', 'offer_closed');

    // 10. The scheduler closes the competition.
    test()->travel(5)->seconds();
    test()->artisan('competitions:tick')->assertSuccessful();
    e2eGet($base, $issuer['token'])->assertOk()->assertJsonPath('data.status', 'closed');

    // 11. Award to the leading offer.
    $award = e2ePost("{$base}/award", ['participant_id' => $winnerParticipantId, 'message_to_winner' => 'Congratulations'], $issuer['token'])
        ->assertCreated()
        ->assertJsonPath('data.amount_minor', 1_060_000)
        ->assertJsonPath('data.is_leading_offer', true);
    $awardId = (string) $award->json('data.id');

    e2eGet($base, $issuer['token'])->assertOk()->assertJsonPath('data.status', 'awarded');
    e2eGet("{$base}/live", $supplierA['token'])->assertOk()->assertJsonPath('data.result.outcome', 'won');
    $loser = e2eGet("{$base}/live", $supplierB['token'])
        ->assertOk()
        ->assertJsonPath('data.result.outcome', 'not_selected')
        ->assertJsonPath('data.result.winning_amount_minor', null);
    e2eAssertNoLeak($loser, $supplierA, [1_000_500, 1_020_000]);

    // 12. Result PDF.
    e2eAssertReportDownloads($base, $issuer['token'], $supplierA['token']);

    // 13. Invoices: the subscription and the sponsorship pass, cleared, with PDFs.
    $invoices = e2eGet('/api/app/v1/billing/invoices', $issuer['token'])->assertOk()->json('data');
    expect(collect($invoices)->pluck('payment_id')->sort()->values()->all())
        ->toBe(collect([$subscriptionPayment['id'], $sponsorshipPayment['id']])->sort()->values()->all());

    foreach ($invoices as $invoice) {
        expect($invoice['einvoice_status'])->toBe('cleared')
            ->and($invoice['number'])->toStartWith('BAFO-INV-2026-')
            ->and($invoice['pdf']['available'])->toBeTrue();

        Auth::forgetGuards();
        $pdf = test()->get($invoice['pdf']['download_path'], e2eHeaders($issuer['token']))->assertOk();
        expect($pdf->streamedContent())->toStartWith('%PDF');
    }

    // 14. Webhooks: signed deliveries of the whole lifecycle reached the ERP.
    $types = e2eVerifiedWebhookTypes($secret);
    expect($types)->toContain('competition.published', 'offer.submitted', 'competition.extended', 'competition.closed', 'award.issued');

    $awardHook = e2eWebhookPayload('award.issued');
    expect($awardHook['data']['object']['id'])->toBe($awardId)
        ->and($awardHook['data']['object']['amount_minor'])->toBe(1_060_000)
        ->and($awardHook['organization_id'])->toBe($issuer['organization']->public_id);

    $deliveries = e2eGet('/api/app/v1/integrations/webhook-endpoints/'.$endpoint->json('data.id').'/deliveries', $issuer['token'])->assertOk()->json('data');
    expect(collect($deliveries)->pluck('status')->unique()->values()->all())->toBe(['succeeded']);

    // 15. The ERP reads the award with OAuth2 client credentials.
    $accessToken = e2eErpAccessToken($issuer['token']);

    test()->getJson("/api/public/v1/awards/{$awardId}", ['Authorization' => 'Bearer '.$accessToken])
        ->assertOk()
        ->assertJsonPath('data.object', 'award')
        ->assertJsonPath('data.id', $awardId)
        ->assertJsonPath('data.amount_minor', 1_060_000)
        ->assertJsonPath('data.competition.id', $competitionId)
        ->assertJsonPath('data.winner.organization.id', $supplierA['organization']->public_id);

    Auth::forgetGuards();
    test()->getJson('/api/public/v1/awards', ['Authorization' => 'Bearer '.$accessToken])
        ->assertOk()
        ->assertJsonPath('data.0.id', $awardId);

    // The token carries only awards:read.
    Auth::forgetGuards();
    test()->getJson("/api/public/v1/competitions/{$competitionId}", ['Authorization' => 'Bearer '.$accessToken])
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');
})->group('e2e');

it('runs a tender from registration to an award read by the ERP', function () {
    // 1. Issuer: register, OTP, login, API access, subscription on the fake checkout, webhooks.
    $issuer = e2eRegister('buyer', '1010900011', '300000001000003');
    e2eAdminFeatures($issuer['organization'], ['api_enabled' => true]);
    $subscriptionPayment = e2eSubscribe($issuer['token'], 'pro');

    $endpoint = e2ePost('/api/app/v1/integrations/webhook-endpoints', [
        'url' => E2E_WEBHOOK_URL, 'event_types' => ['competition.closed', 'award.issued'],
    ], $issuer['token'])->assertCreated();
    $secret = (string) $endpoint->json('data.secret');

    // 2. Suppliers: A buys a plan, B starts the trial.
    $supplierA = e2eRegister('vendor-a', '1010900012', '300000001100003');
    $supplierB = e2eRegister('vendor-b', '1010900013', '300000001200003');
    $vendorPayment = e2eSubscribe($supplierA['token'], 'single');
    e2ePost('/api/app/v1/billing/trial', [], $supplierB['token'])->assertCreated()->assertJsonPath('data.source', 'trial');
    e2ePost('/api/app/v1/billing/trial', [], $supplierB['token'])->assertStatus(409)->assertJsonPath('code', 'trial_not_available');

    // 3. A live tender with a ceiling, a reserve, a 1% step, a 30-minute final window where
    //    offers must beat the leading offer, prices and ranks shown, and the amount published.
    $competitionId = (string) e2ePost('/api/app/v1/competitions', [
        'title' => 'توريد أجهزة حاسب محمول',
        'description' => 'مناقصة لتوريد أجهزة الحاسب.',
        'category_id' => Category::query()->where('code', 'it_hardware')->value('public_id'),
        'region_id' => Region::query()->where('code', 'RIY')->value('public_id'),
        'direction' => 'tender',
        'format' => 'live',
        'rules' => [
            'start_price_minor' => 5_000_000,
            'reserve_price_minor' => 4_800_000,
            'min_step_minor' => null,
            'min_step_bps' => 100,
            'amount_granularity_minor' => 100,
            'must_beat' => 'best',
            'rank_visibility' => 'full',
            'show_prices' => true,
            'auto_extend' => ['enabled' => true, 'window_seconds' => 120, 'by_seconds' => 120, 'max_extensions' => 2],
            'final_window_minutes' => 30,
            'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
            'min_participants' => 2,
            'result_publication' => 'outcome_and_amount',
        ],
        'bidding_opens_at' => '2026-10-06T07:00:00Z',
        'scheduled_close_at' => '2026-10-06T09:00:00Z',
    ], $issuer['token'])->assertCreated()->assertJsonPath('data.direction', 'tender')->json('data.id');
    $base = "/api/app/v1/competitions/{$competitionId}";

    // 4. Publish needs two invitations; then invite both and publish.
    e2ePost("{$base}/publish", [], $issuer['token'])->assertUnprocessable()->assertJsonPath('code', 'min_participants_not_met');

    e2ePost("{$base}/invitations", ['invitations' => [
        ['email' => $supplierA['email']],
        ['email' => $supplierB['email']],
    ]], $issuer['token'])->assertCreated();

    e2ePost("{$base}/publish", [], $issuer['token'])->assertOk()->assertJsonPath('data.status', 'scheduled');

    // 5. Both join on their own plans (paid and trial).
    foreach ([$supplierA, $supplierB] as $supplier) {
        $invitation = e2eInvitation($competitionId, $supplier['token']);
        expect($invitation['access']['coverage'])->toBe('own_plan');
        e2ePost('/api/app/v1/invitations/'.$invitation['invitation']['id'].'/join', ['accept_terms' => true], $supplier['token'])->assertOk();
    }

    // 6. Initial phase: offers are sealed-like (no standing shown) and each participant only has
    //    to improve its own offer.
    test()->travelTo(CarbonImmutable::parse('2026-10-06 07:00:01'));
    test()->artisan('competitions:tick')->assertSuccessful();

    e2eOffer($competitionId, $supplierA['token'], 5_100_000)->assertUnprocessable()->assertJsonPath('code', 'offer_start_price');
    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierA['token'], 4_950_000)->assertCreated()->assertJsonPath('data.live.phase', 'initial');
    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierB['token'], 4_900_000)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', null)
        ->assertJsonPath('data.live.rank', null)
        ->assertJsonPath('data.live.leading_amount_minor', null);
    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierA['token'], 4_940_000)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'offer_step_not_met')
        ->assertJsonPath('details.required_amount_minor', 4_900_500); // 1% below its own 4 950 000

    $initial = e2eGet("{$base}/live", $supplierA['token'])
        ->assertOk()
        ->assertJsonPath('data.phase', 'initial')
        ->assertJsonPath('data.is_leading', null)
        ->assertJsonPath('data.leading_amount_minor', null)
        ->assertJsonPath('data.required_next_amount_minor', 4_900_500);
    e2eAssertNoLeak($initial, $supplierB, [4_900_000, 4_800_000]);

    // 7. Final window: offers must beat the leading offer by 1%.
    test()->travelTo(CarbonImmutable::parse('2026-10-06 08:40:00'));
    test()->artisan('competitions:tick')->assertSuccessful();

    $live = e2eGet("{$base}/live", $supplierA['token'])
        ->assertOk()
        ->assertJsonPath('data.phase', 'final_window')
        ->assertJsonPath('data.leading_amount_minor', 4_900_000)
        ->assertJsonPath('data.rank', 2)
        ->assertJsonPath('data.required_next_amount_minor', 4_851_000);

    // Prices and ranks are shown, but never the other participant's identity or the reserve.
    e2eAssertNoLeak($live, $supplierB, [4_800_000]);
    expect(collect($live->json('data.ladder'))->pluck('is_me')->all())->toBe([false, true]);

    e2eOffer($competitionId, $supplierA['token'], 4_860_000)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'offer_step_not_met')
        ->assertJsonPath('details.required_amount_minor', 4_851_000);

    test()->travel(3)->seconds();
    e2eOffer($competitionId, $supplierA['token'], 4_851_000)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', true)
        ->assertJsonPath('data.live.rank', 1)
        ->assertJsonPath('data.live.extension_count', 0);

    // 8. Anti-sniping: B takes the lead 45 s before the close.
    test()->travelTo(CarbonImmutable::parse('2026-10-06 08:59:15'));
    // 1% below A's 4 851 000 (48 510), on the 1-riyal granularity.
    $required = (int) e2eGet("{$base}/live", $supplierB['token'])->assertOk()->json('data.required_next_amount_minor');
    expect($required)->toBeLessThanOrEqual(4_851_000 - 48_510)
        ->and($required % 100)->toBe(0);

    $snipe = e2eOffer($competitionId, $supplierB['token'], 4_750_000)
        ->assertCreated()
        ->assertJsonPath('data.live.is_leading', true)
        ->assertJsonPath('data.live.extension_count', 1)
        ->assertJsonPath('data.live.effective_close_at', '2026-10-06T09:01:15.000Z');
    e2eAssertNoLeak($snipe, $supplierA, [4_800_000]);

    // 9. Close by the scheduler after the extended close.
    test()->travelTo(CarbonImmutable::parse('2026-10-06 09:01:20'));
    test()->artisan('competitions:tick')->assertSuccessful();

    $issuerLive = e2eGet("{$base}/live", $issuer['token'])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.leader.amount_minor', 4_750_000)
        ->assertJsonPath('data.leader.organization.id', $supplierB['organization']->public_id)
        ->assertJsonPath('data.reserve_met', true);

    // 10. Award the leading offer; the amount is published to the participants.
    $awardId = (string) e2ePost("{$base}/award", ['participant_id' => $issuerLive->json('data.leader.participant_id')], $issuer['token'])
        ->assertCreated()
        ->assertJsonPath('data.amount_minor', 4_750_000)
        ->assertJsonPath('data.reserve_met', true)
        ->json('data.id');

    e2eGet("{$base}/live", $supplierB['token'])->assertOk()->assertJsonPath('data.result.outcome', 'won');
    $loser = e2eGet("{$base}/live", $supplierA['token'])
        ->assertOk()
        ->assertJsonPath('data.result.outcome', 'not_selected')
        ->assertJsonPath('data.result.winning_amount_minor', 4_750_000);
    e2eAssertNoLeak($loser, $supplierB, [4_800_000]);

    // 11. Result PDF, invoices.
    e2eAssertReportDownloads($base, $issuer['token'], $supplierB['token']);

    expect(collect(e2eGet('/api/app/v1/billing/invoices', $issuer['token'])->assertOk()->json('data'))->pluck('payment_id')->all())
        ->toBe([$subscriptionPayment['id']])
        ->and(collect(e2eGet('/api/app/v1/billing/invoices', $supplierA['token'])->assertOk()->json('data'))->pluck('einvoice_status')->all())
        ->toBe(['cleared'])
        ->and($vendorPayment['invoice_id'] ?? null)->not->toBeNull();

    // 12. Signed webhooks for the subscribed types only.
    expect(collect(e2eVerifiedWebhookTypes($secret))->unique()->sort()->values()->all())->toBe(['award.issued', 'competition.closed'])
        ->and(e2eWebhookPayload('award.issued')['data']['object']['id'])->toBe($awardId);

    // 13. The ERP reads the award.
    $accessToken = e2eErpAccessToken($issuer['token']);

    test()->getJson("/api/public/v1/awards/{$awardId}", ['Authorization' => 'Bearer '.$accessToken])
        ->assertOk()
        ->assertJsonPath('data.amount_minor', 4_750_000)
        ->assertJsonPath('data.vat_minor', 712_500)
        ->assertJsonPath('data.amount_incl_vat_minor', 5_462_500)
        ->assertJsonPath('data.competition.direction', 'tender')
        ->assertJsonPath('data.winner.organization.id', $supplierB['organization']->public_id);
})->group('e2e');

/**
 * GET …/report until ready (the report job runs inline), then the issuer downloads the PDF and a
 * participant cannot.
 */
function e2eAssertReportDownloads(string $base, string $issuerToken, string $participantToken): void
{
    $report = e2eGet("{$base}/report?locale=en", $issuerToken);

    if ($report->status() === 202) {
        $report = e2eGet("{$base}/report?locale=en", $issuerToken);
    }

    $report->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.locale', 'en');

    Auth::forgetGuards();
    $download = test()->get((string) $report->json('data.file.download_path'), e2eHeaders($issuerToken))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($download->streamedContent())->toStartWith('%PDF');

    e2eGet("{$base}/report", $participantToken)->assertForbidden();
    test()->getJson((string) $report->json('data.file.download_path'), e2eHeaders($participantToken))->assertForbidden();
}
