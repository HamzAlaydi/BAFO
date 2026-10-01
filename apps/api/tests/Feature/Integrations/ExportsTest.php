<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Events\ExportFinished;
use App\Modules\Integrations\Events\ImportFinished;
use App\Modules\Integrations\Jobs\ProcessExport;
use App\Modules\Integrations\Jobs\ProcessVendorImport;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetReader;
use App\Support\Files\File;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Integrations\IntegrationsFixtures;

beforeEach(function () {
    Storage::fake('private');
});

/**
 * @return list<list<string>>
 */
function integrationsExportRows(ExportJob $job): array
{
    $file = File::query()->findOrFail($job->file_id);
    $bytes = Storage::disk('private')->get($file->path);
    $path = tempnam(sys_get_temp_dir(), 'exp').'.'.$file->extension;
    file_put_contents($path, $bytes);
    $rows = array_values(iterator_to_array((new SpreadsheetReader)->rows($path, $file->extension)));
    unlink($path);

    return $rows;
}

function integrationsExport(array $body): ExportJob
{
    test()->postJson('/api/app/v1/integrations/exports', $body)->assertStatus(202);

    return ExportJob::query()->latest('id')->firstOrFail();
}

/**
 * A participant with one offer and its standing in `$competition`, from a vendor with an ERP key.
 */
function integrationsBidder(Competition $competition, int $amount, int $rank, ?string $erpKey = null): Participant
{
    $supplier = Organization::factory()->create();
    $vendor = Vendor::factory()->for(Organization::query()->findOrFail($competition->organization_id))->linkedTo($supplier)->create();
    ExternalRef::factory()->create([
        'organization_id' => $competition->organization_id, 'refable_type' => 'vendor', 'refable_id' => $vendor->id,
        'system' => 'sap_s4', 'type' => 'supplier', 'value' => $erpKey ?? 'V-'.$rank,
    ]);
    $invitation = Invitation::factory()->joined()->forVendor($vendor)->create(['competition_id' => $competition->id, 'organization_id' => $supplier->id]);
    $participant = Participant::factory()->create(['competition_id' => $competition->id, 'organization_id' => $supplier->id, 'invitation_id' => $invitation->id]);
    $offer = Offer::factory()->amount($amount)->create(['participant_id' => $participant->id]);
    ParticipantStanding::factory()->withOffer($offer)->create(['rank' => $rank, 'is_leader' => $rank === 1]);

    return $participant;
}

it('exports results as CSV with a BOM, decimal SAR and the issuer projection', function () {
    Event::fake([ExportFinished::class]);
    [$organization] = IntegrationsFixtures::signIn();
    $competition = Competition::factory()->for($organization)->closed()->create();
    $leader = integrationsBidder($competition, 9_800_000, 1);
    integrationsBidder($competition, 11_000_050, 2);
    Award::factory()->create(['offer_id' => Offer::query()->where('participant_id', $leader->id)->value('id')]);

    $job = integrationsExport(['type' => 'results', 'format' => 'csv', 'competition_id' => strtoupper($competition->public_id)]);

    expect($job->status)->toBe(JobStatus::Completed)
        ->and($job->row_count)->toBe(2)
        ->and($job->filters)->toBe(['competition_id' => $competition->public_id]);

    $bytes = Storage::disk('private')->get(File::query()->findOrFail($job->file_id)->path);
    $rows = integrationsExportRows($job);

    expect(str_starts_with((string) $bytes, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($rows[0])->toBe(['reference_no', 'title', 'direction', 'format', 'closed_at (KSA)', 'participant_name', 'cr_number', 'vat_number',
            'vendor_external_id', 'rank', 'current_amount (SAR, excl. VAT)', 'first_amount (SAR, excl. VAT)', 'offers_count',
            'last_offer_at (KSA)', 'is_leader', 'awarded'])
        ->and($rows[1][5])->toBe($leader->organization->name)
        ->and($rows[1][8])->toBe('V-1')
        ->and(array_slice($rows[1], 9, 3))->toBe(['1', '98000.00', '98000.00'])
        ->and(array_slice($rows[1], 14, 2))->toBe(['Y', 'Y'])
        ->and($rows[2][10])->toBe('110000.50')
        ->and(array_slice($rows[2], 14, 2))->toBe(['N', 'N']);

    Event::assertDispatched(ExportFinished::class);
});

it('leaves amounts and ranks empty in a sealed competition before the offers are opened', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $competition = Competition::factory()->for($organization)->sealed()->live()->create();
    integrationsBidder($competition, 5_000_000, 1);

    $results = integrationsExportRows(integrationsExport(['type' => 'results', 'format' => 'xlsx', 'competition_id' => $competition->public_id]));
    $log = integrationsExportRows(integrationsExport(['type' => 'offer_log', 'format' => 'csv', 'competition_id' => $competition->public_id]));

    expect(array_slice($results[1], 9, 3))->toBe(['', '', ''])
        ->and($results[1][14])->toBe('')
        ->and($log[1][4])->toBe('');
});

it('exports the offer log in sequence with voids and Riyadh times', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $competition = Competition::factory()->for($organization)->live()->create();
    $participant = integrationsBidder($competition, 7_000_000, 1);
    $second = Offer::factory()->amount(6_900_000)->create(['participant_id' => $participant->id]);
    OfferVoid::factory()->create(['offer_id' => $second->id]);

    $rows = integrationsExportRows(integrationsExport(['type' => 'offer_log', 'format' => 'csv', 'competition_id' => $competition->public_id]));

    expect($rows[0])->toBe(['reference_no', 'seq', 'accepted_at (KSA)', 'participant_name', 'amount (SAR, excl. VAT)', 'stage', 'voided'])
        ->and(array_column(array_slice($rows, 1), 1))->toBe(['1', '2'])
        ->and($rows[1][2])->toBe($second->accepted_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s.v') === $rows[2][2] ? $rows[1][2] : $rows[1][2])
        ->and($rows[1][2])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/')
        ->and([$rows[1][4], $rows[1][6]])->toBe(['70000.00', 'N'])
        ->and([$rows[2][4], $rows[2][6]])->toBe(['69000.00', 'Y']);
});

it('exports awards in a date range with VAT and ERP references', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $competition = Competition::factory()->for($organization)->awarded()->create();
    $participant = integrationsBidder($competition, 1_000_000, 1);
    $award = Award::factory()->create([
        'offer_id' => Offer::query()->where('participant_id', $participant->id)->value('id'),
        'awarded_at' => now()->subDays(2),
    ]);
    ExternalRef::factory()->create([
        'organization_id' => $organization->id, 'refable_type' => 'award', 'refable_id' => $award->id,
        'system' => 'sap_s4', 'type' => 'purchase_order', 'value' => '4500001234',
    ]);
    $old = Competition::factory()->for($organization)->awarded()->create();
    Award::factory()->create(['offer_id' => Offer::query()->where('participant_id', integrationsBidder($old, 5000, 1, 'V-old')->id)->value('id'), 'awarded_at' => now()->subYear()]);
    $foreign = Competition::factory()->awarded()->create();
    Award::factory()->create(['offer_id' => Offer::query()->where('participant_id', integrationsBidder($foreign, 5000, 1, 'V-foreign')->id)->value('id')]);

    $rows = integrationsExportRows(integrationsExport([
        'type' => 'awards', 'format' => 'csv', 'from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString(),
    ]));

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['award_id', 'reference_no', 'title', 'direction', 'awarded_at (KSA)', 'status', 'winner_name', 'cr_number',
            'vat_number', 'vendor_external_system', 'vendor_external_id', 'amount (SAR, excl. VAT)', 'vat_rate', 'vat_amount (SAR)',
            'amount_incl_vat (SAR)', 'justification', 'erp_sync_status', 'erp_refs'])
        ->and($rows[1][0])->toBe($award->public_id)
        ->and(array_slice($rows[1], 9, 6))->toBe(['sap_s4', 'V-1', '10000.00', '15%', '1500.00', '11500.00'])
        ->and($rows[1][17])->toBe('sap_s4:purchase_order:4500001234');

    expect(integrationsExportRows(integrationsExport(['type' => 'awards', 'format' => 'csv'])))->toHaveCount(3);
});

it('exports vendors with the import columns, guarding formula cells', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $region = Region::factory()->create(['code' => 'RIY']);
    $vendor = Vendor::factory()->for($organization)->create(['name' => '=HYPERLINK("http://x")', 'region_id' => $region->id]);
    $vendor->categories()->attach(Category::factory()->create(['code' => 'logistics']));
    ExternalRef::factory()->create([
        'organization_id' => $organization->id, 'refable_type' => 'vendor', 'refable_id' => $vendor->id,
        'system' => 'odoo', 'type' => 'supplier', 'value' => '42',
    ]);

    $rows = integrationsExportRows(integrationsExport(['type' => 'vendors', 'format' => 'xlsx']));

    expect($rows[0])->toBe(['external_system', 'external_id', 'name', 'name_en', 'cr_number', 'vat_number', 'email', 'contact_name',
        'phone', 'region_code', 'city', 'category_codes', 'status', 'linked'])
        ->and($rows[1][0])->toBe('odoo')
        ->and($rows[1][1])->toBe('42')
        ->and($rows[1][2])->toBe('\'=HYPERLINK("http://x")')
        ->and($rows[1][9])->toBe('RIY')
        ->and($rows[1][11])->toBe('logistics')
        ->and($rows[1][13])->toBe('N');
});

it('validates the export request', function () {
    IntegrationsFixtures::signIn();
    $foreign = Competition::factory()->closed()->create();

    test()->postJson('/api/app/v1/integrations/exports', ['type' => 'results', 'format' => 'csv'])
        ->assertJsonValidationErrors(['competition_id']);
    test()->postJson('/api/app/v1/integrations/exports', ['type' => 'results', 'format' => 'csv', 'competition_id' => $foreign->public_id])
        ->assertJsonValidationErrors(['competition_id']);
    test()->postJson('/api/app/v1/integrations/exports', ['type' => 'vendors', 'format' => 'pdf', 'from' => '2026-01-01'])
        ->assertJsonValidationErrors(['format', 'from']);
    test()->postJson('/api/app/v1/integrations/exports', ['type' => 'awards', 'format' => 'csv', 'from' => '2026-02-01', 'to' => '2026-01-01'])
        ->assertJsonValidationErrors(['to']);

    expect(ExportJob::query()->count())->toBe(0);
});

it('lets integration managers of the organization download the export file only', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $job = integrationsExport(['type' => 'vendors', 'format' => 'csv']);
    $file = File::query()->findOrFail($job->file_id);

    test()->getJson('/api/app/v1/integrations/exports/'.$job->public_id)
        ->assertOk()
        ->assertJsonPath('data.file.id', $file->public_id)
        ->assertJsonPath('data.file.download_path', '/api/app/v1/files/'.$file->public_id.'/download')
        ->assertJsonPath('data.status', 'completed');
    test()->get('/api/app/v1/files/'.$file->public_id.'/download')->assertOk();

    IntegrationsFixtures::signIn(OrgRole::Member, organization: $organization);
    test()->getJson('/api/app/v1/files/'.$file->public_id.'/download')->assertForbidden();
    test()->getJson('/api/app/v1/integrations/exports/'.$job->public_id)->assertForbidden();

    IntegrationsFixtures::signIn();
    test()->getJson('/api/app/v1/files/'.$file->public_id.'/download')->assertForbidden();
    test()->getJson('/api/app/v1/integrations/exports/'.$job->public_id)->assertNotFound();
});

it('marks an import or export failed when its worker gives up', function () {
    Event::fake([ExportFinished::class, ImportFinished::class]);
    $export = ExportJob::factory()->create(['status' => JobStatus::Processing]);
    $import = ImportJob::factory()->create(['status' => JobStatus::Processing]);
    $done = ExportJob::factory()->create(['status' => JobStatus::Completed]);

    (new ProcessExport($export->id))->failed(null);
    (new ProcessVendorImport($import->id))->failed(null);
    (new ProcessExport($done->id))->failed(null);

    expect($export->refresh()->status)->toBe(JobStatus::Failed)
        ->and($export->failure_message)->not->toBeNull()
        ->and($import->refresh()->status)->toBe(JobStatus::Failed)
        ->and($done->refresh()->status)->toBe(JobStatus::Completed);

    Event::assertDispatchedTimes(ExportFinished::class, 1);
    Event::assertDispatchedTimes(ImportFinished::class, 1);
});
