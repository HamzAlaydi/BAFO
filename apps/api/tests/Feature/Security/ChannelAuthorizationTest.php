<?php

declare(strict_types=1);

use App\Modules\Bidding\Broadcasting\Channels\BiddingChannels;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UserChannel;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\Support\Bidding\Scenario;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-06: the Reverb channel authorisation. The
 * app v1 account gate does not run on /broadcasting/auth, so the channel callbacks apply it
 * themselves: a suspended organization, an inactive membership or an unverified user listens to
 * nothing, and nobody listens to another organization's participant channel, the issuer channel
 * of a competition it did not issue, or another user's channel.
 */

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'security-key',
            'secret' => 'security-secret',
            'app_id' => 'security',
            'options' => ['host' => 'localhost', 'port' => 8085, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => [],
        ],
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    BiddingChannels::register();
    Broadcast::channel(UserChannel::NAME, UserChannel::class);
});

function securityChannelAuth(object $test, User $user, string $channel): TestResponse
{
    Auth::forgetGuards();
    $token = $user->createToken('test')->plainTextToken;

    return $test->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel], ['Authorization' => "Bearer {$token}"]);
}

function securityParticipantChannel(Scenario $s, int $index): string
{
    return 'private-competition.'.$s->competition->public_id.'.participant.'.$s->participant($index)->organization->public_id;
}

it('refuses every channel to the members of a suspended organization', function () {
    $s = Scenario::make($this);

    securityChannelAuth($this, $s->bidder(0), securityParticipantChannel($s, 0))->assertOk();
    securityChannelAuth($this, $s->issuer, 'private-competition.'.$s->competition->public_id)->assertOk();

    $s->participant(0)->organization->forceFill(['status' => OrganizationStatus::Suspended])->save();
    $s->issuerOrganization->forceFill(['status' => OrganizationStatus::Suspended])->save();

    securityChannelAuth($this, $s->bidder(0), securityParticipantChannel($s, 0))->assertForbidden();
    securityChannelAuth($this, $s->issuer, 'private-competition.'.$s->competition->public_id)->assertForbidden();
});

it('refuses a user whose e-mail is not verified', function () {
    $s = Scenario::make($this);
    $s->bidder(0)->forceFill(['email_verified_at' => null])->save();

    securityChannelAuth($this, $s->bidder(0), securityParticipantChannel($s, 0))->assertForbidden();
});

it('refuses a deactivated team member', function () {
    $s = Scenario::make($this);
    Membership::query()->where('user_id', $s->bidder(0)->id)->update(['status' => MembershipStatus::Inactive]);

    securityChannelAuth($this, $s->bidder(0), securityParticipantChannel($s, 0))->assertForbidden();
});

it('never lets a participant listen to another participant, to the issuer or to a look-alike name', function () {
    $s = Scenario::make($this);
    $competition = $s->competition->public_id;
    $own = $s->participant(0)->organization->public_id;

    foreach ([
        securityParticipantChannel($s, 1),
        'private-competition.'.$competition,
        'private-competition.'.$competition.'.participant.'.$own.'x',
        'private-competition.'.$competition.'x.participant.'.$own,
        'private-competition.'.$competition.'.participant.'.$own.'.participant.'.$own,
        'presence-competition.'.$competition,
    ] as $channel) {
        expect(securityChannelAuth($this, $s->bidder(0), $channel)->status())->toBeIn([403], $channel);
    }

    // The own channel is case-insensitive on the ids and still works.
    securityChannelAuth($this, $s->bidder(0), 'private-competition.'.strtoupper($competition).'.participant.'.strtoupper($own))->assertOk();
});

it('never lets an issuer of another competition listen to this one', function () {
    $s = Scenario::make($this);
    $other = Scenario::make($this);

    securityChannelAuth($this, $other->issuer, 'private-competition.'.$s->competition->public_id)->assertForbidden();
    securityChannelAuth($this, $other->issuer, securityParticipantChannel($s, 0))->assertForbidden();
});

it('refuses another user\'s notification channel', function () {
    $s = Scenario::make($this);
    $stranger = User::factory()->withMembership(Organization::factory()->create(), OrgRole::Owner)->create();

    securityChannelAuth($this, $s->bidder(0), 'private-user.'.$s->bidder(0)->public_id)->assertOk();
    securityChannelAuth($this, $s->bidder(0), 'private-user.'.$stranger->public_id)->assertForbidden();
});
