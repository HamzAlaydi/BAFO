<?php

declare(strict_types=1);

use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Platform\TestUser;

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('public');
});

function platformStoredFile(FilePurpose $purpose = FilePurpose::CompetitionAttachment, array $attributes = []): File
{
    $file = File::factory()->purpose($purpose)->create([
        'organization_id' => 7,
        'original_name' => 'specs.pdf',
        'size_bytes' => 9,
        ...$attributes,
    ]);
    Storage::disk($file->disk)->put($file->path, '%PDF-1.4');

    return $file;
}

it('requires a token', function () {
    $file = platformStoredFile();

    $this->getJson($file->downloadPath())
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('streams the file as an attachment when the purpose rule allows the user', function () {
    app(FileAccessRegistry::class)->register('competition_attachment',
        fn (Authenticatable $user, File $file): bool => $user->getAttribute('organization_id') === $file->organization_id);
    $file = platformStoredFile();
    TestUser::actingAs(organizationId: 7);

    $response = $this->get($file->downloadPath())->assertOk();

    expect($response->streamedContent())->toBe('%PDF-1.4')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('Content-Disposition'))->toContain('specs.pdf')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Request-Id'))->not->toBeNull();
});

it('encodes non-ASCII file names with RFC 5987', function () {
    app(FileAccessRegistry::class)->register(FilePurpose::CompetitionAttachment, fn (): bool => true);
    $file = platformStoredFile(attributes: ['original_name' => 'كراسة الشروط.pdf']);
    TestUser::actingAs();

    $disposition = (string) $this->get($file->downloadPath())->assertOk()->headers->get('Content-Disposition');

    expect($disposition)->toContain("filename*=utf-8''".rawurlencode('كراسة الشروط.pdf'))
        ->and($disposition)->toMatch('/filename="?[\x20-\x7e]+"?;/');
});

it('forbids a user the purpose rule denies', function () {
    app(FileAccessRegistry::class)->register('competition_attachment', fn (): bool => false);
    $file = platformStoredFile();
    TestUser::actingAs();

    $this->getJson($file->downloadPath())
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

it('forbids private files whose purpose has no rule', function () {
    $file = platformStoredFile(FilePurpose::InvoicePdf);
    TestUser::actingAs();

    $this->getJson($file->downloadPath())->assertForbidden();
});

it('serves public purposes to any signed-in user', function () {
    $file = platformStoredFile(FilePurpose::OrganizationLogo, ['original_name' => 'logo.png']);
    TestUser::actingAs();

    $this->get($file->downloadPath())->assertOk();
});

it('answers not_found for unknown and malformed ids', function (string $id) {
    TestUser::actingAs();

    $this->getJson("/api/app/v1/files/{$id}/download")
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
})->with([
    'unknown ulid' => fn () => File::newPublicId(),
    'malformed' => 'not-a-ulid',
]);

it('accepts the id in any casing', function () {
    app(FileAccessRegistry::class)->register('competition_attachment', fn (): bool => true);
    $file = platformStoredFile();
    TestUser::actingAs();

    $this->get('/api/app/v1/files/'.strtoupper($file->public_id).'/download')->assertOk();
});

it('answers not_found when the bytes are missing', function () {
    app(FileAccessRegistry::class)->register('competition_attachment', fn (): bool => true);
    $file = platformStoredFile();
    Storage::disk('private')->delete($file->path);
    TestUser::actingAs();

    $this->getJson($file->downloadPath())->assertNotFound();
});

it('is named app.v1.files.download', function () {
    expect(route('app.v1.files.download', ['file' => '01j00000000000000000000000'], false))
        ->toBe('/api/app/v1/files/01j00000000000000000000000/download');
});
