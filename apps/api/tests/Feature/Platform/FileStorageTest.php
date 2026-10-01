<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Resources\FileResource;
use App\Support\Exceptions\ApiException;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 10, 5));
});

/**
 * A real PDF header so finfo sniffs application/pdf.
 */
function platformPdfUpload(string $name = 'specs.pdf', int $kilobytes = 0): UploadedFile
{
    $bytes = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $bytes.str_repeat(' ', $kilobytes * 1024));
}

function platformCatchApiException(Closure $callback): ApiException
{
    try {
        $callback();
    } catch (ApiException $e) {
        return $e;
    }

    throw new RuntimeException('Expected an ApiException.');
}

it('stores an upload on the purpose disk under {purpose}/{yyyy}/{mm}/{public_id}.{ext}', function () {
    $upload = platformPdfUpload('كراسة الشروط.PDF');

    $file = app(FileStorage::class)->store($upload, FilePurpose::CompetitionAttachment, 7, 3);

    expect($file->exists)->toBeTrue()
        ->and($file->disk)->toBe('private')
        ->and($file->path)->toBe("competition_attachment/2026/10/{$file->public_id}.pdf")
        ->and($file->extension)->toBe('pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->original_name)->toBe('كراسة الشروط.PDF')
        ->and($file->organization_id)->toBe(7)
        ->and($file->uploaded_by_user_id)->toBe(3)
        ->and($file->size_bytes)->toBe($upload->getSize())
        ->and($file->sha256)->toBe(hash_file('sha256', $upload->getRealPath()));

    Storage::disk('private')->assertExists($file->path);
});

it('stores logos on the public disk with an absolute public url', function () {
    $file = app(FileStorage::class)->store(UploadedFile::fake()->image('logo.png', 64, 64), FilePurpose::OrganizationLogo, 7, 3);

    expect($file->disk)->toBe('public')
        ->and(app(FileStorage::class)->publicUrl($file))->toStartWith('http')
        ->and(app(FileStorage::class)->publicUrl($file))->toEndWith($file->path);

    Storage::disk('public')->assertExists($file->path);
});

it('has no public url for private files', function () {
    $file = app(FileStorage::class)->store(platformPdfUpload(), FilePurpose::CompetitionAttachment, 7, 3);

    expect(app(FileStorage::class)->publicUrl($file))->toBeNull();
});

it('rejects an extension the purpose does not allow', function () {
    $e = platformCatchApiException(fn () => app(FileStorage::class)->store(
        UploadedFile::fake()->createWithContent('script.exe', 'MZ'), FilePurpose::CompetitionAttachment, 7, 3,
    ));

    expect($e->errorCode)->toBe('file_type_not_allowed')
        ->and($e->status)->toBe(422)
        ->and($e->errors)->toHaveKey('file')
        ->and($e->details['allowed_extensions'])->toContain('pdf', 'docx');

    expect(File::query()->count())->toBe(0);
});

it('rejects content that does not match the extension', function () {
    $e = platformCatchApiException(fn () => app(FileStorage::class)->store(
        UploadedFile::fake()->createWithContent('specs.pdf', 'just some text'), FilePurpose::CompetitionAttachment, 7, 3,
    ));

    expect($e->errorCode)->toBe('file_type_not_allowed');
});

it('rejects files over the purpose limit', function () {
    $e = platformCatchApiException(fn () => app(FileStorage::class)->store(
        UploadedFile::fake()->image('avatar.png')->size(3 * 1024), FilePurpose::UserAvatar, null, 3,
    ));

    expect($e->errorCode)->toBe('file_too_large')
        ->and($e->status)->toBe(422)
        ->and($e->details)->toBe(['max_bytes' => 2 * 1024 * 1024]);
});

it('renders the upload errors with the localised message on the file field', function () {
    Route::middleware('app_v1')->withoutMiddleware('auth:sanctum')
        ->post('api/app/v1/__test/upload', function (Request $request) {
            app(FileStorage::class)->store($request->file('file'), FilePurpose::OrganizationProfile, 1, 1);
        });

    $this->post('/api/app/v1/__test/upload', ['file' => UploadedFile::fake()->createWithContent('x.docx', 'PK')], [
        'Accept-Language' => 'en',
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_type_not_allowed')
        ->assertJsonPath('message', 'This file type is not allowed. Allowed types: pdf.')
        ->assertJsonPath('errors.file.0', 'This file type is not allowed. Allowed types: pdf.')
        ->assertJsonPath('details.allowed_extensions', ['pdf']);
});

it('stores generated contents', function () {
    $file = app(FileStorage::class)->storeContents('%PDF-1.4 report', 'report.pdf', 'application/pdf', FilePurpose::CompetitionReport, 9);

    expect($file->disk)->toBe('private')
        ->and($file->path)->toBe("competition_report/2026/10/{$file->public_id}.pdf")
        ->and($file->uploaded_by_user_id)->toBeNull()
        ->and($file->size_bytes)->toBe(15)
        ->and($file->sha256)->toBe(hash('sha256', '%PDF-1.4 report'));

    expect(Storage::disk('private')->get($file->path))->toBe('%PDF-1.4 report');
});

it('deletes the row and, after commit, the bytes', function () {
    $file = app(FileStorage::class)->storeContents('a,b', 'export.csv', 'text/csv', FilePurpose::Export, 9);

    DB::transaction(function () use ($file) {
        app(FileStorage::class)->delete($file);

        Storage::disk('private')->assertExists($file->path);
    });

    expect(File::query()->whereKey($file->id)->exists())->toBeFalse();
    Storage::disk('private')->assertMissing($file->path);
});

it('exposes the API.md File shape through FileResource', function () {
    $file = app(FileStorage::class)->store(platformPdfUpload(), FilePurpose::CompetitionAttachment, 7, 3);

    expect((new FileResource($file))->resolve(request()))->toBe([
        'id' => $file->public_id,
        'name' => 'specs.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => $file->size_bytes,
        'download_path' => "/api/app/v1/files/{$file->public_id}/download",
        'created_at' => '2026-10-05T'.$file->created_at->format('H:i:s').'.000Z',
    ]);
});

it('describes every purpose', function (FilePurpose $purpose) {
    expect($purpose->disk())->toBeIn(['public', 'private'])
        ->and($purpose->allowedExtensions())->not->toBeEmpty()
        ->and($purpose->label('ar'))->not->toBe($purpose->value)
        ->and($purpose->label('en'))->not->toBe($purpose->value)
        ->and($purpose->maxBytes() === null)->toBe($purpose->isGenerated());
})->with(FilePurpose::cases());
