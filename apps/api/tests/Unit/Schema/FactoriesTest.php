<?php

declare(strict_types=1);

use App\Modules\Admin\Models\Admin;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentLine;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookEvent;
use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a valid, persisted row with each factory', function (string $modelClass) {
    /** @var class-string<Model> $modelClass */
    $model = $modelClass::factory()->create();

    expect($model->exists)->toBeTrue()
        ->and($modelClass::query()->whereKey($model->getKey())->exists())->toBeTrue();

    if (in_array(HasPublicId::class, class_uses_recursive($modelClass), true)) {
        expect($model->getAttribute('public_id'))->toMatch('/^[0-9a-z]{26}$/');
    }
})->with([
    Region::class, Category::class, CloseReason::class, CompetitionPreset::class,
    Organization::class, User::class, Membership::class, OtpCode::class, Consent::class, AccountDeletionRequest::class,
    Vendor::class, ExternalRef::class, ApiClient::class, ApiKey::class, WebhookEndpoint::class, WebhookEvent::class,
    WebhookDelivery::class, ImportJob::class, ExportJob::class,
    Competition::class, CompetitionExtension::class, CompetitionAttachment::class, Invitation::class, Participant::class,
    Comment::class,
    CompetitionLiveState::class, Offer::class, OfferVoid::class, OfferRejection::class, ParticipantStanding::class,
    BafoRound::class, Award::class, CompetitionReport::class,
    Plan::class, Coupon::class, Payment::class, PaymentLine::class, CouponRedemption::class, Subscription::class,
    CompetitionSponsorship::class, SponsoredPass::class, Invoice::class, InvoiceLine::class,
    DeviceToken::class,
    Admin::class,
]);

it('creates valid rows with the factory states', function (Model $model) {
    expect($model->exists)->toBeTrue()
        ->and($model::query()->whereKey($model->getKey())->exists())->toBeTrue();
})->with([
    'organization with owner' => fn () => Organization::factory()->withOwner()->verified()->create(),
    'suspended organization' => fn () => Organization::factory()->suspended()->notVatRegistered()->create(),
    'incomplete billing profile' => fn () => Organization::factory()->incompleteBillingProfile()->create(),
    'user with admin membership' => fn () => User::factory()->withMembership(role: OrgRole::Admin)->create(),
    'unverified user' => fn () => User::factory()->unverified()->create(),
    'invited member' => fn () => Membership::factory()->invited('plain-token')->create(),
    'expired otp' => fn () => OtpCode::factory()->forUser(User::factory()->create())->expired()->create(),
    'organization deletion' => fn () => AccountDeletionRequest::factory()->organizationScope()->create(),
    'blocked vendor' => fn () => Vendor::factory()->blocked()->create(),
    'revoked api client' => fn () => ApiClient::factory()->revoked()->create(),
    'expired api key' => fn () => ApiKey::factory()->expired()->create(),
    'failed delivery' => fn () => WebhookDelivery::factory()->failed()->create(),
    'completed import' => fn () => ImportJob::factory()->completed()->create(),
    'scheduled competition' => fn () => Competition::factory()->scheduled()->create(),
    'live auction' => fn () => Competition::factory()->auction()->live()->create(),
    'closed sealed competition' => fn () => Competition::factory()->sealed()->closed()->create(),
    'competition in final window' => fn () => Competition::factory()->inFinalWindow()->create(),
    'competition in bafo round' => fn () => Competition::factory()->inBafoRound()->create(),
    'awarded competition' => fn () => Competition::factory()->awarded()->create(),
    'not awarded competition' => fn () => Competition::factory()->notAwarded()->create(),
    'cancelled competition' => fn () => Competition::factory()->cancelled()->create(),
    'external link attachment' => fn () => CompetitionAttachment::factory()->externalLink()->create(),
    'joined invitation' => fn () => Invitation::factory()->joined()->create(),
    'revoked invitation' => fn () => Invitation::factory()->revoked()->create(),
    'sponsored participant' => fn () => Participant::factory()->sponsored()->create(),
    'participant question' => fn () => Comment::factory()->byParticipant(Participant::factory()->create())->create(),
    'ended bafo round' => fn () => BafoRound::factory()->ended()->create(),
    'revoked non-leading award' => fn () => Award::factory()->notLeading()->revoked()->create(),
    'custom plan' => fn () => Plan::factory()->custom()->create(),
    'voucher' => fn () => Coupon::factory()->voucher()->create(),
    'succeeded sponsorship payment' => fn () => Payment::factory()->sponsorship()->succeeded()->create(),
    'trial subscription' => fn () => Subscription::factory()->trial()->create(),
    'granted subscription' => fn () => Subscription::factory()->grant()->create(),
    'pending subscription' => fn () => Subscription::factory()->pendingPayment()->create(),
    'selected sponsorship' => fn () => CompetitionSponsorship::factory()->selected(5)->funded(2)->create(),
    'pending pass' => fn () => SponsoredPass::factory()->pending()->create(),
    'cleared invoice' => fn () => Invoice::factory()->cleared()->create(),
    'ios device' => fn () => DeviceToken::factory()->ios()->create(),
    'super admin' => fn () => Admin::factory()->superAdmin()->create(),
]);

it('generates Saudi-format organization and user data', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    expect($organization->cr_number)->toMatch('/^\d{10}$/')
        ->and($organization->vat_number)->toMatch('/^3\d{13}3$/')
        ->and($organization->phone)->toMatch('/^\+9665\d{8}$/')
        ->and($organization->name)->toStartWith('شركة ')
        ->and($organization->email)->toBe(mb_strtolower($organization->email))
        ->and($organization->isBillingProfileComplete())->toBeTrue()
        ->and($user->phone)->toMatch('/^\+9665\d{8}$/')
        ->and($user->locale)->toBe('ar')
        ->and(Hash::check('password', (string) $user->password))->toBeTrue()
        ->and($vendor->vat_number)->toMatch('/^3\d{13}3$/');
});

it('derives the published schedule of the lifecycle states (§7.2)', function () {
    $live = Competition::factory()->live()->create();
    $close = $live->scheduled_close_at;

    expect($live->status)->toBe(CompetitionStatus::Live)
        ->and($live->reference_no)->toMatch('/^BAFO-T-\d{4}-\d{6}$/')
        ->and($live->effective_close_at?->equalTo($close))->toBeTrue()
        ->and($live->final_window_starts_at?->equalTo($close?->subMinutes(60)))->toBeTrue()
        ->and($live->invitation_cutoff_at?->equalTo($live->final_window_starts_at))->toBeTrue()
        ->and($live->hard_stop_at?->equalTo($close?->addSeconds(10 * 180)))->toBeTrue()
        ->and($live->opened_at)->not->toBeNull()
        ->and($live->closed_at)->toBeNull();

    $auction = Competition::factory()->auction()->scheduled()->create();

    expect($auction->reference_no)->toMatch('/^BAFO-A-\d{4}-\d{6}$/')
        ->and($auction->organization()->firstOrFail()->auction_enabled)->toBeTrue()
        ->and($auction->opened_at)->toBeNull()
        ->and($auction->final_window_starts_at)->toBeNull()
        ->and($auction->invitation_cutoff_at?->equalTo($auction->scheduled_close_at?->subMinutes(60)))->toBeTrue();

    $sealed = Competition::factory()->sealed()->closed()->create();

    expect($sealed->hard_stop_at)->toBeNull()
        ->and($sealed->offers_opened_at?->equalTo($sealed->closed_at))->toBeTrue();
});

it('computes the live phase from the stored schedule', function () {
    $now = CarbonImmutable::now();

    expect(Competition::factory()->live()->create()->phaseAt($now))->toBe(Phase::Initial)
        ->and(Competition::factory()->inFinalWindow()->create()->phaseAt($now))->toBe(Phase::FinalWindow)
        ->and(Competition::factory()->withoutFinalWindow()->live()->create()->phaseAt($now))->toBe(Phase::Open)
        ->and(Competition::factory()->sealed()->live()->create()->phaseAt($now))->toBe(Phase::Sealed)
        ->and(Competition::factory()->scheduled()->create()->phaseAt($now))->toBeNull();
});

it('keeps the participant graph consistent', function () {
    $participant = Participant::factory()->create();
    $invitation = $participant->invitation()->firstOrFail();
    $joinedBy = $participant->joinedBy()->firstOrFail();

    expect($invitation->competition_id)->toBe($participant->competition_id)
        ->and($invitation->organization_id)->toBe($participant->organization_id)
        ->and($participant->alias_no)->toBeBetween(1, 99)
        ->and($joinedBy->membership()->firstOrFail()->organization_id)->toBe($participant->organization_id)
        ->and($joinedBy->hasPermission(Permission::ParticipationSubmitOffers))->toBeTrue()
        ->and($participant->competition()->firstOrFail()->participants()->pluck('id')->all())->toBe([$participant->id]);
});

it('links users, memberships and organizations in both directions', function () {
    $organization = Organization::factory()->withOwner()->create();
    $owner = $organization->ownerMembership()->firstOrFail()->user()->firstOrFail();
    $member = User::factory()->withMembership($organization)->create();

    expect($owner->organization()->firstOrFail()->is($organization))->toBeTrue()
        ->and($organization->users()->pluck('users.id')->sort()->values()->all())->toBe(collect([$owner->id, $member->id])->sort()->values()->all())
        ->and($owner->hasPermission(Permission::AccountDeleteOrganization))->toBeTrue()
        ->and($member->hasPermission(Permission::TeamManage))->toBeFalse()
        ->and($member->createToken('test')->accessToken->tokenable_type)->toBe('user');
});
