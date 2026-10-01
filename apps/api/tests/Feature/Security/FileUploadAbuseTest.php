<?php

declare(strict_types=1);

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Bidding\Scenario;
use Tests\Support\Identity\Accounts;

/*
 * Security review (docs/build/SECURITY_REVIEW.md): uploads cannot choose where they land, cannot
 * smuggle a script or markup behind an allowed extension, and private files follow their access
 * rule (an invitee sees invitation documents only; nobody outside sees anything).
 */

const SECURITY_PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

it('stores an upload under a server-chosen name whatever the client file name says', function (string $name, string $expectedOriginal) {
    $file = app(FileStorage::class)->store(UploadedFile::fake()->createWithContent($name, SECURITY_PDF), FilePurpose::CompetitionAttachment, 1, 1);

    expect($file->path)->toMatch('#^competition_attachment/\d{4}/\d{2}/[0-9a-z]{26}\.pdf$#')
        ->and($file->original_name)->toBe($expectedOriginal);
    Storage::disk('private')->assertExists($file->path);
})->with([
    'parent directories' => ['../../../../etc/passwd.pdf', 'passwd.pdf'],
    'windows separators' => ['..\\..\\boot.pdf', 'boot.pdf'],
    'control characters' => ["evil\x00\r\n.pdf", 'evil.pdf'],
]);

it('refuses content that does not match its allowed extension', function (string $name, string $bytes, FilePurpose $purpose) {
    expect(fn () => app(FileStorage::class)->store(UploadedFile::fake()->createWithContent($name, $bytes), $purpose, 1, 1))
        ->toThrow(ApiException::class, 'file_type_not_allowed');
})->with([
    'PHP behind .pdf' => ['invoice.pdf', "<?php system(\$_GET['c']); ?>", FilePurpose::CompetitionAttachment],
    'PHP behind a double extension' => ['shell.php.pdf', '<?php echo 1; ?>', FilePurpose::CompetitionAttachment],
    'HTML behind .png' => ['logo.png', '<html><script>alert(1)</script></html>', FilePurpose::OrganizationLogo],
    'SVG behind .png' => ['logo.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', FilePurpose::OrganizationLogo],
    'SVG extension' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>', FilePurpose::OrganizationLogo],
    'HTML extension' => ['page.html', '<html></html>', FilePurpose::CompetitionAttachment],
    'executable' => ['setup.exe', "MZ\x90\x00", FilePurpose::CompetitionAttachment],
]);

it('refuses an upload over the purpose limit', function () {
    $upload = UploadedFile::fake()->createWithContent('logo.png', "\x89PNG\r\n\x1a\n".str_repeat("\0", 2 * 1024 * 1024 + 1));

    expect(fn () => app(FileStorage::class)->store($upload, FilePurpose::OrganizationLogo, 1, 1))
        ->toThrow(ApiException::class, 'file_too_large');
});

it('lets an invitee download invitation documents only, and nobody outside anything', function () {
    $s = Scenario::make($this, bidders: 1);
    $document = CompetitionAttachment::factory()->create(['competition_id' => $s->competition->id]);
    $letter = CompetitionAttachment::factory()->invitationDocument()->create(['competition_id' => $s->competition->id]);

    foreach ([$document, $letter] as $attachment) {
        Storage::disk('private')->put($attachment->file->path, SECURITY_PDF);
    }

    $inviteeOrganization = Organization::factory()->create();
    $invitee = Accounts::member($inviteeOrganization, OrgRole::Owner);
    Invitation::factory()->forOrganization($inviteeOrganization)->create(['competition_id' => $s->competition->id, 'status' => InvitationStatus::Sent]);

    $stranger = Accounts::member(Organization::factory()->create(), OrgRole::Owner);
    $download = fn (User $user, CompetitionAttachment $a) => $this->get('/api/app/v1/files/'.$a->file->public_id.'/download', Accounts::headers($user));

    $download($s->bidder(0), $document)->assertOk();
    $download($invitee, $letter)->assertOk();
    $download($invitee, $document)->assertForbidden();
    $download($stranger, $document)->assertForbidden();
    $download($stranger, $letter)->assertForbidden();
});
