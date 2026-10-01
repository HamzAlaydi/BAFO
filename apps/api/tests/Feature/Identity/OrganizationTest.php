<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Accounts::seedCatalog();
});

it('returns the own view of the organization', function () {
    $organization = Organization::factory()->incompleteBillingProfile()->create([
        'region_id' => Region::query()->where('code', 'RIY')->value('id'),
    ]);
    $organization->categories()->sync(Category::query()->whereIn('code', ['it_hardware', 'consulting'])->pluck('id'));
    $user = Accounts::member($organization);

    $response = $this->getJson('/api/app/v1/organization', Accounts::headers($user))
        ->assertOk()
        ->assertJsonPath('data.id', $organization->public_id)
        ->assertJsonPath('data.region', [
            'id' => Region::query()->where('code', 'RIY')->value('public_id'),
            'code' => 'RIY',
            'name' => 'الرياض',
        ])
        ->assertJsonPath('data.features', ['api_enabled' => false, 'auction_enabled' => false, 'sponsorship_enabled' => false])
        ->assertJsonPath('data.billing_profile_complete', false)
        ->assertJsonPath('data.billing_profile_missing', [
            'legal_name_ar',
            'national_address.building_number',
            'national_address.street',
            'national_address.district',
            'national_address.postal_code',
        ])
        ->assertJsonPath('data.trial_available', true)
        ->assertJsonPath('data.verified', false)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.logo_url', null)
        ->assertJsonPath('data.profile_document', null);

    expect(collect($response->json('data.categories'))->pluck('code')->all())->toBe(['it_hardware', 'consulting'])
        ->and($response->json('data.national_address'))->toHaveKeys(['building_number', 'street', 'district', 'postal_code', 'additional_number', 'short_address']);
});

it('requires a token', function () {
    $this->getJson('/api/app/v1/organization')->assertUnauthorized();
});

describe('PATCH /organization', function () {
    it('updates the profile, the categories and the national address', function () {
        Event::fake([OrganizationUpdated::class]);
        $user = Accounts::owner(Organization::factory()->create(['vat_registered' => false, 'vat_number' => null]));
        $region = Region::query()->where('code', 'EAS')->sole();

        $this->patchJson('/api/app/v1/organization', [
            'name' => 'شركة الأفق الجديدة',
            'region_id' => $region->public_id,
            'city' => 'الدمام',
            'vat_registered' => true,
            'vat_number' => '311111111111113',
            'national_address' => ['building_number' => '4321', 'postal_code' => '31111'],
            'category_ids' => [Category::query()->where('code', 'logistics')->value('public_id')],
            'visible_in_suggestions' => false,
        ], Accounts::headers($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'شركة الأفق الجديدة')
            ->assertJsonPath('data.region.code', 'EAS')
            ->assertJsonPath('data.vat_number', '311111111111113')
            ->assertJsonPath('data.national_address.building_number', '4321')
            ->assertJsonPath('data.national_address.postal_code', '31111')
            ->assertJsonPath('data.categories.0.code', 'logistics')
            ->assertJsonPath('data.visible_in_suggestions', false);

        Event::assertDispatched(OrganizationUpdated::class, fn (OrganizationUpdated $event): bool => in_array('category_ids', $event->changedFields, true)
            && in_array('vat_number', $event->changedFields, true)
            && in_array('address_building_number', $event->changedFields, true));

        expect(AuditLog::query()->where('action', 'organization.updated')->sole()->changes)->toHaveKeys(['name', 'region_id', 'category_ids']);
    });

    it('keeps the fields that are not sent', function () {
        $organization = Organization::factory()->create();
        $user = Accounts::owner($organization);

        $this->patchJson('/api/app/v1/organization', ['city' => 'جدة'], Accounts::headers($user))->assertOk();

        expect($organization->refresh())
            ->city->toBe('جدة')
            ->address_street->not->toBeNull()
            ->vat_number->not->toBeNull();
    });

    it('clears the VAT number when the organization is no longer VAT registered', function () {
        $user = Accounts::owner();

        $this->patchJson('/api/app/v1/organization', ['vat_registered' => false], Accounts::headers($user))
            ->assertOk()
            ->assertJsonPath('data.vat_number', null);
    });

    it('requires a VAT number for a VAT-registered organization', function () {
        $user = Accounts::owner(Organization::factory()->notVatRegistered()->create());

        $this->patchJson('/api/app/v1/organization', ['vat_registered' => true], Accounts::headers($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vat_number']);
    });

    it('refuses to change the CR number', function () {
        $user = Accounts::owner();

        $this->patchJson('/api/app/v1/organization', ['cr_number' => '1010999999'], Accounts::headers($user))
            ->assertUnprocessable()
            ->assertJsonPath('errors.cr_number.0', __('identity.validation.cr_number_immutable'));
    });

    it('validates the fields', function () {
        $this->patchJson('/api/app/v1/organization', ['name' => '', 'website' => 'ftp://x', 'region_id' => 'nope'], Accounts::headers(Accounts::owner()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'website', 'region_id']);
    });

    it('needs organization.update', function () {
        $user = Accounts::member(Organization::factory()->create(), OrgRole::Member);

        $this->patchJson('/api/app/v1/organization', ['name' => ''], Accounts::headers($user))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    });

    it('lets an admin update the profile', function () {
        $user = Accounts::member(Organization::factory()->create(), OrgRole::Admin);

        $this->patchJson('/api/app/v1/organization', ['city' => 'تبوك'], Accounts::headers($user))->assertOk();
    });
});

describe('logo and profile document', function () {
    beforeEach(function () {
        Storage::fake('public');
        Storage::fake('private');
    });

    it('uploads and removes the logo', function () {
        $user = Accounts::owner();
        $headers = [...Accounts::headers($user), 'Accept' => 'application/json'];

        $url = $this->post('/api/app/v1/organization/logo', ['file' => UploadedFile::fake()->image('logo.png')], $headers)
            ->assertOk()
            ->json('data.logo_url');

        $file = File::query()->sole();
        expect($url)->toContain('/storage/organization_logo/')
            ->and($file->purpose)->toBe(FilePurpose::OrganizationLogo)
            ->and($file->organization_id)->toBe($user->membership->organization_id);

        $this->delete('/api/app/v1/organization/logo', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        expect(File::query()->count())->toBe(0)
            ->and(AuditLog::query()->pluck('action')->all())->toContain('organization.logo_updated', 'organization.logo_removed');
    });

    it('uploads the company profile as a private PDF', function () {
        $user = Accounts::owner();
        $headers = [...Accounts::headers($user), 'Accept' => 'application/json'];

        $this->post('/api/app/v1/organization/profile-document', ['file' => UploadedFile::fake()->createWithContent('profile.pdf', "%PDF-1.4\n%%EOF\n")], $headers)
            ->assertOk()
            ->assertJsonPath('data.profile_document.name', 'profile.pdf')
            ->assertJsonPath('data.profile_document.extension', 'pdf')
            ->assertJsonStructure(['data' => ['profile_document' => ['id', 'name', 'mime_type', 'extension', 'size_bytes', 'download_path', 'created_at']]]);

        expect(File::query()->sole()->disk)->toBe('private');
    });

    it('rejects a logo that is not an image', function () {
        $headers = [...Accounts::headers(Accounts::owner()), 'Accept' => 'application/json'];

        $this->post('/api/app/v1/organization/logo', ['file' => UploadedFile::fake()->createWithContent('logo.pdf', "%PDF-1.4\n")], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'file_type_not_allowed');
    });

    it('needs organization.update', function () {
        $user = Accounts::member(Organization::factory()->create(), OrgRole::Member);

        $this->post('/api/app/v1/organization/logo', ['file' => UploadedFile::fake()->image('logo.png')], [...Accounts::headers($user), 'Accept' => 'application/json'])
            ->assertForbidden();
    });

    it('lets members of the organization and issuers of its competitions download the profile', function () {
        $owner = Accounts::owner();
        $this->post('/api/app/v1/organization/profile-document', ['file' => UploadedFile::fake()->createWithContent('profile.pdf', "%PDF-1.4\n%%EOF\n")], [...Accounts::headers($owner), 'Accept' => 'application/json'])
            ->assertOk();
        $file = File::query()->sole();
        $path = '/api/app/v1/files/'.$file->public_id.'/download';

        $colleague = Accounts::member($owner->membership->organization);
        $this->get($path, Accounts::headers($colleague))->assertOk();

        $stranger = Accounts::owner();
        $this->getJson($path, Accounts::headers($stranger))->assertForbidden();

        $issuer = Accounts::owner();
        $competition = Competition::factory()->create(['organization_id' => $issuer->membership->organization_id]);
        Participant::factory()->create(['competition_id' => $competition->id, 'organization_id' => $owner->membership->organization_id]);
        $this->get($path, Accounts::headers($issuer))->assertOk();
    });
});
