<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\Award;
use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Key constraints of ARCHITECTURE §5 (uniques, partial uniques, checks, delete rules). Each
 * violating write runs in its own savepoint so the test transaction stays usable.
 */

/**
 * The SQLSTATE of the query exception thrown by `$write` (23505 unique, 23514 check,
 * 23503 foreign key), or null when it succeeds.
 */
function schemaSqlState(Closure $write): ?string
{
    try {
        DB::transaction($write);
    } catch (QueryException $exception) {
        return (string) ($exception->errorInfo[0] ?? $exception->getCode());
    }

    return null;
}

it('allows one owner per organization and one membership per user', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->owner()->for($organization)->create();

    expect(schemaSqlState(fn () => Membership::factory()->owner()->for($organization)->create()))->toBe('23505')
        ->and(schemaSqlState(fn () => Membership::factory()->admin()->for($organization)->create()))->toBeNull()
        ->and(schemaSqlState(fn () => Membership::factory()->owner()->create()))->toBeNull();

    $user = User::factory()->withMembership()->create();

    expect(schemaSqlState(fn () => Membership::factory()->for($user)->create()))->toBe('23505');
});

it('keeps organization CR numbers and user e-mails unique, lowercased', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email' => '  Sara@Issuer.SA ']);

    expect($user->email)->toBe('sara@issuer.sa')
        ->and(schemaSqlState(fn () => Organization::factory()->create(['cr_number' => $organization->cr_number])))->toBe('23505')
        ->and(schemaSqlState(fn () => User::factory()->create(['email' => 'SARA@issuer.sa'])))->toBe('23505');
});

it('allows one pending account deletion request per user', function () {
    $request = AccountDeletionRequest::factory()->create();
    $again = fn () => AccountDeletionRequest::factory()->create([
        'user_id' => $request->user_id,
        'organization_id' => $request->organization_id,
    ]);

    expect(schemaSqlState($again))->toBe('23505');

    $request->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

    expect(schemaSqlState($again))->toBeNull();
});

it('checks the competition price and step columns', function () {
    expect(schemaSqlState(fn () => Competition::factory()->create(['start_price_minor' => 0])))->toBe('23514')
        ->and(schemaSqlState(fn () => Competition::factory()->create(['reserve_price_minor' => -100])))->toBe('23514')
        ->and(schemaSqlState(fn () => Competition::factory()->create(['min_step_minor' => 0, 'min_step_bps' => null])))->toBe('23514')
        ->and(schemaSqlState(fn () => Competition::factory()->create(['min_step_minor' => 50_000, 'min_step_bps' => 50])))->toBe('23514')
        ->and(schemaSqlState(fn () => Competition::factory()->create(['min_step_minor' => 50_000, 'min_step_bps' => null])))->toBeNull();
});

it('keeps invitations and participants unique per competition', function () {
    $participant = Participant::factory()->create();
    $invitation = Invitation::query()->findOrFail($participant->invitation_id);

    expect(schemaSqlState(fn () => Invitation::factory()->create([
        'competition_id' => $invitation->competition_id,
        'email' => strtoupper($invitation->email),
    ])))->toBe('23505')
        // same organization twice (through a second invitation)
        ->and(schemaSqlState(fn () => Participant::factory()->create([
            'competition_id' => $participant->competition_id,
            'organization_id' => $participant->organization_id,
            'invitation_id' => Invitation::factory()->joined()->create([
                'competition_id' => $participant->competition_id,
                'organization_id' => $participant->organization_id,
            ]),
        ])))->toBe('23505')
        // same alias twice
        ->and(schemaSqlState(fn () => Participant::factory()->create([
            'competition_id' => $participant->competition_id,
            'alias_no' => $participant->alias_no,
        ])))->toBe('23505');
});

it('allows one issued award per competition', function () {
    $award = Award::factory()->create();
    $again = fn () => Award::factory()->create(['offer_id' => $award->offer_id]);

    expect(schemaSqlState($again))->toBe('23505');

    $award->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

    expect(schemaSqlState($again))->toBeNull();
});

it('allows one live sponsored pass per invitation', function () {
    $pass = SponsoredPass::factory()->create();
    $again = fn () => SponsoredPass::factory()->create([
        'sponsorship_id' => $pass->sponsorship_id,
        'invitation_id' => $pass->invitation_id,
    ]);

    expect(schemaSqlState($again))->toBe('23505');

    $pass->forceFill(['status' => 'released', 'release_reason' => PassReleaseReason::Declined, 'released_at' => now()])->save();

    expect(schemaSqlState($again))->toBeNull();
});

it('keeps vendor e-mails and external refs unique per organization', function () {
    $vendor = Vendor::factory()->create();
    $ref = ExternalRef::factory()->create();

    expect(schemaSqlState(fn () => Vendor::factory()->create([
        'organization_id' => $vendor->organization_id,
        'email' => $vendor->email,
    ])))->toBe('23505')
        ->and(schemaSqlState(fn () => Vendor::factory()->create(['email' => $vendor->email])))->toBeNull()
        ->and(schemaSqlState(fn () => ExternalRef::factory()->create([
            'organization_id' => $ref->organization_id,
            'refable_type' => $ref->refable_type,
            'refable_id' => $ref->refable_id,
            'system' => $ref->system,
            'type' => $ref->type,
            'value' => $ref->value,
        ])))->toBe('23505');
});

it('keeps payment idempotency keys unique per organization', function () {
    $payment = Payment::factory()->create(['idempotency_key' => 'checkout-key-0001']);

    expect(schemaSqlState(fn () => Payment::factory()->create([
        'organization_id' => $payment->organization_id,
        'idempotency_key' => 'checkout-key-0001',
    ])))->toBe('23505')
        ->and(schemaSqlState(fn () => Payment::factory()->count(2)->create([
            'organization_id' => $payment->organization_id,
            'idempotency_key' => null,
        ])))->toBeNull();
});

it('restricts and cascades deletes as the contract says', function () {
    $organization = Organization::factory()->withOwner()->create();
    $region = Region::query()->findOrFail($organization->region_id);

    // regions ← organizations: RESTRICT
    expect(schemaSqlState(fn () => $region->delete()))->toBe('23503');

    // organizations → memberships: CASCADE
    $membershipId = $organization->ownerMembership()->firstOrFail()->id;
    $organization->forceDelete();

    expect(Membership::query()->whereKey($membershipId)->exists())->toBeFalse();
});

it('rejects float money on the halala columns', function () {
    $competition = Competition::factory()->make();

    expect(fn () => $competition->setAttribute('start_price_minor', 12.5))->toThrow(InvalidArgumentException::class);
});
