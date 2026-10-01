<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\ApiScope;
use Illuminate\Support\Facades\DB;
use Tests\Support\Identity\Accounts;
use Tests\Support\Integrations\IntegrationsFixtures;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-08: one-time secrets never sit in clear text
 * in the queue (Redis in production, and `failed_jobs` when a mail fails) nor in the idempotency
 * store. The OTP mail and the team invitation mail are encrypted on the queue, like the
 * competition invitation mail; stored idempotent responses are encrypted at rest.
 */

beforeEach(function () {
    config(['queue.default' => 'database']);
});

/**
 * @return list<string>
 */
function securityQueuedPayloads(): array
{
    return DB::table('jobs')->pluck('payload')->map(static fn (mixed $payload): string => (string) $payload)->all();
}

it('does not queue an OTP code in clear text', function () {
    $organization = Organization::factory()->create();
    User::factory()->unverified()->withMembership($organization, OrgRole::Owner)->create(['email' => 'new@acme.sa', 'password' => Accounts::PASSWORD]);

    $this->postJson('/api/app/v1/auth/otp/send', ['email' => 'new@acme.sa', 'purpose' => 'email_verification'])->assertStatus(202);

    $payloads = securityQueuedPayloads();

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0])->not->toContain((string) config('bafo.identity.otp.fake_code'))
        ->and($payloads[0])->not->toContain('OtpCodeMail\\\\";');
});

it('does not queue a team invitation token in clear text', function () {
    $owner = Accounts::owner();
    Subscription::factory()->create([
        'organization_id' => $owner->membership->organization_id,
        'plan_id' => Plan::factory()->create(['seats' => 5])->id,
        'status' => SubscriptionStatus::Active,
        'seats' => 5,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->postJson('/api/app/v1/team/members', ['name' => 'Khalid', 'email' => 'khalid@acme.sa', 'role' => 'member'], Accounts::headers($owner))
        ->assertCreated();

    $payloads = implode("\n", securityQueuedPayloads());

    expect($payloads)->not->toBe('')
        ->and($payloads)->not->toContain('accept-invite')
        ->and($payloads)->not->toContain('#t=');
});

it('keeps a secret shown once out of the idempotency store, and still replays it', function () {
    config(['bafo.integrations.webhooks.allow_private_targets' => false]);
    IntegrationsFixtures::fakeDns();
    [$plain] = IntegrationsFixtures::apiKey(scopes: [ApiScope::WebhooksManage]);
    $headers = [...IntegrationsFixtures::bearer($plain), 'Idempotency-Key' => 'endpoint-create-1'];

    $created = $this->postJson('/api/public/v1/webhook-endpoints', ['url' => 'https://erp.example.sa/h', 'event_types' => ['*']], $headers)
        ->assertCreated();
    $secret = (string) $created->json('data.secret');

    $stored = (string) DB::table('idempotency_keys')->where('key', 'endpoint-create-1')->value('response_body');

    expect($secret)->toStartWith('whsec_')
        ->and($stored)->not->toBe('')
        ->and($stored)->not->toContain($secret);

    // The replay answers with the original body, secret included.
    $this->postJson('/api/public/v1/webhook-endpoints', ['url' => 'https://erp.example.sa/h', 'event_types' => ['*']], $headers)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.secret', $secret)
        ->assertJsonPath('data.id', $created->json('data.id'));
});
