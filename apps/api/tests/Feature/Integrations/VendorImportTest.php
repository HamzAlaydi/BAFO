<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ImportMode;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Events\ImportFinished;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\Spreadsheets\SheetData;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetReader;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetWriter;
use App\Support\Files\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Integrations\IntegrationsFixtures;

beforeEach(function () {
    Storage::fake('private');
    Region::factory()->create(['code' => 'RIY']);
    Category::factory()->create(['code' => 'it_hardware']);
    Category::factory()->create(['code' => 'logistics']);
});

const INTEGRATIONS_IMPORT_HEADER = 'external_system,external_id,name,name_en,cr_number,vat_number,email,contact_name,phone,region_code,city,category_codes,status';

function integrationsCsv(array $lines, string $header = INTEGRATIONS_IMPORT_HEADER): UploadedFile
{
    return UploadedFile::fake()->createWithContent('vendors.csv', "\xEF\xBB\xBF".$header."\n".implode("\n", $lines)."\n");
}

function integrationsImport(UploadedFile $file, ImportMode $mode): TestResponse
{
    return test()->post('/api/app/v1/integrations/imports', ['file' => $file, 'type' => 'vendors', 'mode' => $mode->value], ['Accept' => 'application/json']);
}

/**
 * @return list<list<string>>
 */
function integrationsFileRows(File $file): array
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.'.$file->extension;
    file_put_contents($path, Storage::disk('private')->get($file->path));
    $rows = array_values(iterator_to_array((new SpreadsheetReader)->rows($path, $file->extension)));
    unlink($path);

    return $rows;
}

it('downloads the CSV template with a BOM', function () {
    IntegrationsFixtures::signIn();

    $response = test()->get('/api/app/v1/integrations/imports/templates/vendors?format=csv')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="vendors-import-template.csv"');

    expect($response->getContent())->toBe("\xEF\xBB\xBF".INTEGRATIONS_IMPORT_HEADER."\n");
});

it('downloads the XLSX template with a Read me sheet, lists and dropdowns', function () {
    IntegrationsFixtures::signIn();

    $bytes = test()->get('/api/app/v1/integrations/imports/templates/vendors?format=xlsx')->assertOk()->getContent();
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($workbook)->toContain('name="vendors"', 'name="Read me"', 'name="Lists"')
        ->and($sheet)->toContain('<dataValidations count="3">', 'sqref="J2:J10001"', 'sqref="L2:L10001"', 'sqref="M2:M10001"', 'Lists!$A$2')
        ->and(iterator_to_array((new SpreadsheetReader)->rows($path, 'xlsx', ['name', 'email']))[1])->toBe(explode(',', INTEGRATIONS_IMPORT_HEADER));

    unlink($path);
    test()->get('/api/app/v1/integrations/imports/templates/suppliers')->assertNotFound();
});

it('validates every row without writing anything', function () {
    Event::fake([ImportFinished::class]);
    [$organization] = IntegrationsFixtures::signIn();

    $response = integrationsImport(integrationsCsv([
        'sap_s4,100045,شركة الريادة,Al Riyada,1010987654,300000000000013,sales@riyada.sa,خالد,+966551234567,RIY,الرياض,it_hardware;logistics,active',
        ',,,Nameless,,,x@y.sa,,,,,,',
        'SAP S4,1,Bad System,,123,999,not-an-email,,0551234567,ZZZ,,unknown_cat,archived',
        ',,Duplicate,,,,sales@riyada.sa,,,,,,',
        'odoo,,Missing key,,,,k@k.sa,,,,,,',
    ]), ImportMode::Validate)->assertStatus(202);

    $job = ImportJob::query()->sole();

    expect($response->json('data.id'))->toBe($job->public_id)
        ->and($job->status)->toBe(JobStatus::Completed)
        ->and($job->total_rows)->toBe(5)
        ->and($job->valid_rows)->toBe(1)
        ->and($job->error_rows)->toBe(4)
        ->and($job->created_rows)->toBe(0)
        ->and($job->updated_rows)->toBe(0)
        ->and(Vendor::query()->count())->toBe(0);

    $codes = collect($job->errors_preview)->map(fn (array $e) => $e['row'].':'.$e['column'].':'.$e['code'])->all();

    expect($codes)->toBe([
        '3:name:required',
        '4:external_system:invalid_format',
        '4:email:invalid_format',
        '4:cr_number:invalid_format',
        '4:vat_number:invalid_format',
        '4:phone:invalid_format',
        '4:region_code:unknown_region',
        '4:category_codes:unknown_category',
        '4:status:invalid_format',
        '5:email:duplicate_in_file',
        '6:external_id:required',
    ])->and($job->errors_preview[0]['message'])->toBe('العمود name مطلوب.');

    $errorsFile = File::query()->findOrFail($job->errors_file_id);
    $rows = integrationsFileRows($errorsFile);

    expect($errorsFile->extension)->toBe('csv')
        ->and($rows[0])->toBe([...explode(',', INTEGRATIONS_IMPORT_HEADER), 'errors'])
        ->and($rows)->toHaveCount(5)
        ->and($rows[1][2])->toBe('')
        ->and(end($rows[1]))->toContain('name: ');

    $response->assertJsonPath('data.errors_file.id', $errorsFile->public_id)
        ->assertJsonStructure(['data' => ['id', 'type', 'mode', 'status', 'total_rows', 'valid_rows', 'created_rows', 'updated_rows',
            'error_rows', 'errors_preview', 'errors_file', 'source_file', 'failure_message', 'finished_at', 'created_at']]);

    test()->get('/api/app/v1/files/'.$errorsFile->public_id.'/download')->assertOk();
    Event::assertDispatched(ImportFinished::class);
});

it('commits the valid rows: creates, updates by ERP key and by e-mail, and skips errors', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $byKey = Vendor::factory()->for($organization)->create(['email' => 'old@key.sa', 'city' => 'جدة', 'notes' => 'keep']);
    ExternalRef::factory()->create([
        'organization_id' => $organization->id, 'refable_type' => 'vendor', 'refable_id' => $byKey->id,
        'system' => 'sap_s4', 'type' => 'supplier', 'value' => '1',
    ]);
    $byEmail = Vendor::factory()->for($organization)->blocked()->create(['email' => 'mail@match.sa']);
    $linked = Vendor::factory()->for($organization)->create(['email' => 'taken@other.sa']);

    integrationsImport(integrationsCsv([
        'sap_s4,1,Updated By Key,,,,new@key.sa,,,RIY,,it_hardware,',
        ',,Updated By Mail,,,,MAIL@match.sa,,,,,,',
        'odoo,77,Brand New,,1010987654,,new@vendor.sa,,,,,logistics,blocked',
        ',,Bad,,,,broken,,,,,,',
        'sap_s4,1b,Gets The Key,,,,taken@other.sa,,,,,,',
    ]), ImportMode::Commit)->assertStatus(202);

    $job = ImportJob::query()->sole();

    expect($job->status)->toBe(JobStatus::Completed)
        ->and($job->valid_rows)->toBe(4)
        ->and($job->created_rows)->toBe(1)
        ->and($job->updated_rows)->toBe(3)
        ->and($job->error_rows)->toBe(1);

    $byKey->refresh();
    expect($byKey->name)->toBe('Updated By Key')
        ->and($byKey->email)->toBe('new@key.sa')
        ->and($byKey->city)->toBeNull()
        ->and($byKey->notes)->toBe('keep')
        ->and($byKey->region?->code)->toBe('RIY')
        ->and($byKey->categories->pluck('code')->all())->toBe(['it_hardware']);

    expect($byEmail->refresh()->name)->toBe('Updated By Mail')->and($byEmail->status->value)->toBe('blocked');

    $new = Vendor::query()->where('email', 'new@vendor.sa')->sole();
    expect($new->source)->toBe(VendorSource::Import)
        ->and($new->status->value)->toBe('blocked')
        ->and($new->externalRefs->pluck('value')->all())->toBe(['77']);

    // An unknown ERP key with a known e-mail goes to that vendor.
    expect(ExternalRef::query()->where('value', '1b')->sole()->refable_id)->toBe($linked->id)
        ->and($linked->refresh()->name)->toBe('Gets The Key');
});

it('flags a row whose ERP key vendor would take another vendor e-mail', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $keyed = Vendor::factory()->for($organization)->create(['email' => 'a@a.sa']);
    ExternalRef::factory()->create([
        'organization_id' => $organization->id, 'refable_type' => 'vendor', 'refable_id' => $keyed->id,
        'system' => 'odoo', 'type' => 'supplier', 'value' => '5',
    ]);
    Vendor::factory()->for($organization)->create(['email' => 'b@b.sa']);

    integrationsImport(integrationsCsv(['odoo,5,X,,,,b@b.sa,,,,,,']), ImportMode::Validate);

    expect(ImportJob::query()->sole()->errors_preview[0])->toMatchArray(['row' => 2, 'column' => 'email', 'code' => 'vendor_email_taken']);
});

it('imports an XLSX file and a semicolon CSV with columns in any order', function () {
    IntegrationsFixtures::signIn();
    $xlsx = (new SpreadsheetWriter)->write(ExportFormat::Xlsx, [new SheetData('vendors', [
        ['name', 'email', 'vat_number'],
        ['XLSX Vendor', 'x@xlsx.sa', 300000000000013],
    ])]);

    integrationsImport(UploadedFile::fake()->createWithContent('vendors.xlsx', $xlsx), ImportMode::Commit)->assertStatus(202);
    integrationsImport(UploadedFile::fake()->createWithContent('vendors.csv', "EMAIL;Name\ns@semi.sa;Semi Co\n"), ImportMode::Commit)
        ->assertStatus(202);

    expect(Vendor::query()->where('email', 'x@xlsx.sa')->sole()->vat_number)->toBe('300000000000013')
        ->and(Vendor::query()->where('email', 's@semi.sa')->sole()->name)->toBe('Semi Co')
        ->and(ImportJob::query()->pluck('created_rows')->all())->toBe([1, 1]);
});

it('fails the job when a required column is missing or the file is too long', function () {
    IntegrationsFixtures::signIn();

    integrationsImport(integrationsCsv(['x'], 'name,city'), ImportMode::Validate)->assertStatus(202);

    $job = ImportJob::query()->sole();
    expect($job->status)->toBe(JobStatus::Failed)
        ->and($job->failure_message)->toBe('يجب أن يحتوي الملف على الأعمدة: email.')
        ->and($job->finished_at)->not->toBeNull();

    config(['bafo.integrations.imports.max_rows' => 2]);
    integrationsImport(integrationsCsv([',,A,,,,a@a.sa,,,,,,', ',,B,,,,b@b.sa,,,,,,', ',,C,,,,c@c.sa,,,,,,']), ImportMode::Commit);

    expect(ImportJob::query()->latest('id')->first()?->status)->toBe(JobStatus::Failed)
        ->and(Vendor::query()->count())->toBe(0);
});

it('refuses files that are not CSV or XLSX', function () {
    IntegrationsFixtures::signIn();

    integrationsImport(UploadedFile::fake()->createWithContent('vendors.pdf', '%PDF-1.4'), ImportMode::Validate)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_type_not_allowed');

    test()->post('/api/app/v1/integrations/imports', ['type' => 'suppliers', 'mode' => 'now'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file', 'type', 'mode']);
});

it('shows import jobs to the organization only and needs integrations.manage but not api_enabled', function () {
    [$organization] = IntegrationsFixtures::signIn(apiEnabled: false);
    integrationsImport(integrationsCsv([',,A,,,,a@a.sa,,,,,,']), ImportMode::Validate)->assertStatus(202);
    $job = ImportJob::query()->sole();

    test()->getJson('/api/app/v1/integrations/imports/'.$job->public_id)->assertOk()->assertJsonPath('data.valid_rows', 1);

    IntegrationsFixtures::signIn();
    test()->getJson('/api/app/v1/integrations/imports/'.$job->public_id)->assertNotFound();

    IntegrationsFixtures::signIn(OrgRole::Member, organization: Organization::query()->findOrFail($organization->id));
    test()->getJson('/api/app/v1/integrations/imports/'.$job->public_id)->assertForbidden();
    test()->get('/api/app/v1/integrations/imports/templates/vendors', ['Accept' => 'application/json'])->assertForbidden();
});

it('prunes import jobs and their files after seven days', function () {
    IntegrationsFixtures::signIn();
    integrationsImport(integrationsCsv([',,,,,,x@y.sa,,,,,,']), ImportMode::Validate);
    $job = ImportJob::query()->sole();
    $paths = File::query()->pluck('path')->all();

    $this->travel(8)->days();
    test()->artisan('integrations:prune')->assertSuccessful();

    expect(ImportJob::query()->count())->toBe(0)->and(File::query()->count())->toBe(0);

    foreach ($paths as $path) {
        Storage::disk('private')->assertMissing($path);
    }
});
