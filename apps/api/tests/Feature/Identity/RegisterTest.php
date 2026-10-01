<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Mail\OtpCodeMail;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Accounts::seedCatalog();
    Accounts::publishLegalDocuments();
    Mail::fake();
});

it('registers the organization, its owner and sends the verification code', function () {
    Event::fake([UserRegistered::class]);

    $response = $this->postJson('/api/app/v1/auth/register', Accounts::registration())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['email', 'verification_required', 'otp_expires_at'], 'meta' => ['server_time']])
        ->assertJsonPath('data.email', 'owner@acme.sa')
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonMissingPath('data.token');

    expect($response->json('data.otp_expires_at'))->toBeIso8601Utc();

    $organization = Organization::query()->sole();
    $user = User::query()->sole();
    $membership = $user->membership;

    expect($organization)
        ->name->toBe('شركة المصدر')
        ->cr_number->toBe('1010123456')
        ->email->toBe('owner@acme.sa')
        ->phone->toBe('+966501234567')
        ->status->toBe(OrganizationStatus::Active)
        ->vat_number->toBe('300000000000003')
        ->address_building_number->toBe('1234')
        ->address_short->toBe('RRRD2929')
        ->and($organization->region->code)->toBe('RIY')
        ->and($organization->categories->pluck('code')->all())->toBe(['it_hardware'])
        ->and($organization->isBillingProfileComplete())->toBeTrue()
        ->and($user)
        ->email->toBe('owner@acme.sa')
        ->status->toBe(UserStatus::PendingVerification)
        ->email_verified_at->toBeNull()
        ->locale->toBe('ar')
        ->and($membership)
        ->role->toBe(OrgRole::Owner)
        ->status->toBe(MembershipStatus::Active)
        ->can_award->toBeTrue()
        ->can_purchase->toBeTrue()
        ->organization_id->toBe($organization->id);

    expect(Consent::query()->where('user_id', $user->id)->pluck('document_code')->map->value->sort()->values()->all())
        ->toBe([LegalDocumentCode::Privacy->value, LegalDocumentCode::Terms->value])
        ->and(Consent::query()->first()?->document_version)->toBe('2026-10-01');

    $otp = OtpCode::query()->sole();
    expect($otp->purpose)->toBe(OtpPurpose::EmailVerification)
        ->and($otp->user_id)->toBe($user->id)
        ->and($otp->code_hash)->toBe(OtpCode::hashCode('123456'));

    Mail::assertQueued(OtpCodeMail::class, fn (OtpCodeMail $mail): bool => $mail->hasTo('owner@acme.sa')
        && $mail->code === '123456'
        && $mail->purpose === OtpPurpose::EmailVerification
        && $mail->locale === 'ar');

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->is($user) && $event->organization->is($organization));

    expect(AuditLog::query()->where('action', 'organization.registered')->sole()->organization_id)->toBe($organization->id);
});

it('renders the verification mail in the chosen language', function () {
    $this->postJson('/api/app/v1/auth/register', Accounts::registration(['locale' => 'en']))->assertCreated();

    $mail = Mail::queued(OtpCodeMail::class)->sole();
    $html = $mail->render();

    expect($mail->locale)->toBe('en')
        ->and($html)->toContain('123456')
        ->and($html)->toContain('verify your e-mail address');

    $mail->assertHasSubject('Your BAFO verification code');
});

it('defaults the user language to the request language', function () {
    $payload = Accounts::registration();
    unset($payload['locale']);

    $this->postJson('/api/app/v1/auth/register', $payload, ['Accept-Language' => 'en'])->assertCreated();

    expect(User::query()->sole()->locale)->toBe('en');
});

it('registers an organization that is not VAT registered and without optional fields', function () {
    $payload = Accounts::registration(organization: [
        'vat_registered' => false,
        'vat_number' => null,
        'legal_name_ar' => null,
        'website' => null,
        'national_address' => null,
        'category_ids' => [],
    ]);

    $this->postJson('/api/app/v1/auth/register', $payload)->assertCreated();

    $organization = Organization::query()->sole();

    expect($organization->vat_registered)->toBeFalse()
        ->and($organization->vat_number)->toBeNull()
        ->and($organization->address_building_number)->toBeNull()
        ->and($organization->categories)->toBeEmpty()
        ->and($organization->missingBillingProfileFields())->toContain('legal_name_ar', 'address_building_number');
});

it('validates the required fields', function () {
    $this->postJson('/api/app/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([
            'name', 'email', 'phone', 'password', 'organization', 'organization.name', 'organization.cr_number',
            'organization.region_id', 'organization.city', 'accept_terms', 'accept_privacy',
        ]);

    expect(User::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('validates the Saudi formats', function (array $overrides, array $organization, string $field) {
    $this->postJson('/api/app/v1/auth/register', Accounts::registration($overrides, $organization))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'phone not E.164' => [['phone' => '0501234567'], [], 'phone'],
    'CR with 9 digits' => [[], ['cr_number' => '101012345'], 'organization.cr_number'],
    'VAT not 3…3' => [[], ['vat_number' => '100000000000001'], 'organization.vat_number'],
    'VAT required when registered' => [[], ['vat_registered' => true, 'vat_number' => null], 'organization.vat_number'],
    'website not https' => [[], ['website' => 'http://masdar.sa'], 'organization.website'],
    'building number' => [[], ['national_address' => ['building_number' => '12']], 'organization.national_address.building_number'],
    'postal code' => [[], ['national_address' => ['postal_code' => '1234']], 'organization.national_address.postal_code'],
    'short address' => [[], ['national_address' => ['short_address' => 'rrrd2929']], 'organization.national_address.short_address'],
    'unknown region' => [[], ['region_id' => '01j9zq4m1x2a3b4c5d6e7f8g9h'], 'organization.region_id'],
    'unknown category' => [[], ['category_ids' => ['01j9zq4m1x2a3b4c5d6e7f8g9h']], 'organization.category_ids.0'],
    'weak password' => [['password' => 'password', 'password_confirmation' => 'password'], [], 'password'],
    'password confirmation' => [['password_confirmation' => 'Other-Passw0rd!'], [], 'password'],
    'terms not accepted' => [['accept_terms' => false], [], 'accept_terms'],
    'invalid locale' => [['locale' => 'fr'], [], 'locale'],
]);

it('explains the formats in the request language', function () {
    $errors = $this->postJson('/api/app/v1/auth/register', Accounts::registration(organization: ['cr_number' => '12']), ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->json('errors');

    expect($errors['organization.cr_number'][0])->toBe('The commercial registration number must be exactly 10 digits.');
});

it('rejects an inactive region', function () {
    $payload = Accounts::registration();
    Region::query()->where('code', 'RIY')->update(['is_active' => false]);

    $this->postJson('/api/app/v1/auth/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['organization.region_id']);
});

it('refuses an e-mail that already has an account', function () {
    User::factory()->create(['email' => 'owner@acme.sa']);

    $this->postJson('/api/app/v1/auth/register', Accounts::registration(), ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'An account with this e-mail address already exists.');
});

it('refuses a registered CR number', function () {
    Organization::factory()->create(['cr_number' => '1010123456']);

    $errors = $this->postJson('/api/app/v1/auth/register', Accounts::registration())
        ->assertUnprocessable()
        ->json('errors');

    expect($errors['organization.cr_number'][0])->toBe(__('identity.validation.cr_number_taken'));
});

it('rejects the honeypot', function () {
    $this->postJson('/api/app/v1/auth/register', Accounts::registration(['website_url' => 'https://spam.example']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website_url']);

    expect(Organization::query()->count())->toBe(0);
});

it('rejects an unknown invitation token as a field error', function () {
    $this->postJson('/api/app/v1/auth/register', Accounts::registration(['invitation_token' => 'unknown-token']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['invitation_token']);
});

it('rejects an invitation that is no longer open', function () {
    Invitation::factory()->create(['email' => 'owner@acme.sa', 'status' => InvitationStatus::Expired, 'token_hash' => hash('sha256', 'expired-token')]);

    $this->postJson('/api/app/v1/auth/register', Accounts::registration(['invitation_token' => 'expired-token']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invitation_token']);
});

it('requires the invited e-mail when registering from an invitation', function () {
    Invitation::factory()->create(['email' => 'someone@else.sa', 'status' => InvitationStatus::Sent, 'token_hash' => hash('sha256', 'the-token')]);

    $this->postJson('/api/app/v1/auth/register', Accounts::registration(['invitation_token' => 'the-token']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invitation_email_mismatch')
        ->assertJsonPath('message', __('identity.errors.invitation_email_mismatch'));

    expect(User::query()->count())->toBe(0);
});

it('registers from an invitation sent to the same e-mail', function () {
    Invitation::factory()->create(['email' => 'owner@acme.sa', 'status' => InvitationStatus::Viewed, 'token_hash' => hash('sha256', 'the-token')]);

    $this->postJson('/api/app/v1/auth/register', Accounts::registration(['invitation_token' => 'the-token']))
        ->assertCreated();

    expect(User::query()->where('email', 'owner@acme.sa')->exists())->toBeTrue();
});

it('is rate limited per e-mail', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/app/v1/auth/register', ['email' => 'owner@acme.sa'])->assertUnprocessable();
    }

    $this->postJson('/api/app/v1/auth/register', ['email' => 'owner@acme.sa'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests');
});
