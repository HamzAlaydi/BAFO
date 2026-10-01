<?php

declare(strict_types=1);

use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\AttachmentAdded;
use App\Modules\Competitions\Events\CommentPosted;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Files\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    Storage::fake('private');
    [$this->org, $this->owner] = Fixtures::issuer();
});

function pdfUpload(string $name = 'specs.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

describe('attachments', function (): void {
    it('uploads a private document to a draft', function (): void {
        Event::fake([AttachmentAdded::class]);
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn($this->owner);

        $response = $this->post("/api/app/v1/competitions/{$competition->public_id}/attachments", [
            'file' => pdfUpload(),
            'kind' => 'document',
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'kind', 'title', 'file' => ['id', 'name', 'mime_type', 'extension', 'size_bytes', 'download_path', 'created_at'], 'url', 'is_addendum', 'sort_order', 'created_at']])
            ->assertJsonPath('data.kind', 'document')
            ->assertJsonPath('data.title', 'specs.pdf')
            ->assertJsonPath('data.is_addendum', false)
            ->assertJsonPath('data.url', null);

        $file = File::query()->where('public_id', $response->json('data.file.id'))->firstOrFail();
        Storage::disk('private')->assertExists($file->path);
        Event::assertDispatched(AttachmentAdded::class);
    });

    it('adds an external link as an addendum after publish', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/attachments", [
            'kind' => 'external_link', 'url' => 'https://docs.example.sa/specs', 'title' => 'المواصفات',
        ])->assertCreated()
            ->assertJsonPath('data.file', null)
            ->assertJsonPath('data.url', 'https://docs.example.sa/specs')
            ->assertJsonPath('data.is_addendum', true);
    });

    it('rejects a wrong file type and an http link', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn($this->owner);

        $this->post("/api/app/v1/competitions/{$competition->public_id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('run.exe', 'MZ binary'),
            'kind' => 'document',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'file_type_not_allowed');

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/attachments", [
            'kind' => 'external_link', 'url' => 'http://insecure.example.sa', 'title' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['url']);
    });

    it('returns 409 competition_not_editable once closed', function (): void {
        $competition = Competition::factory()->closed()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/attachments", [
            'kind' => 'external_link', 'url' => 'https://docs.example.sa', 'title' => 'x',
        ])->assertStatus(409)->assertJsonPath('code', 'competition_not_editable');
    });

    it('shows invitees only the invitation documents, and participants everything', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
        CompetitionAttachment::factory()->create(['competition_id' => $competition->id, 'kind' => AttachmentKind::Document]);
        CompetitionAttachment::factory()->create(['competition_id' => $competition->id, 'kind' => AttachmentKind::InvitationDocument]);
        [, , $inviteeOwner] = Fixtures::invitee($competition);
        [, , $participantOwner] = Fixtures::participant($competition);

        Fixtures::signIn($inviteeOwner);
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/attachments")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'invitation_document');

        Fixtures::signIn($participantOwner);
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/attachments")->assertOk()->assertJsonCount(2, 'data');

        [, $stranger] = Fixtures::supplier();
        Fixtures::signIn($stranger);
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/attachments")->assertNotFound();
    });

    it('guards the private file download by the attachment rule (§8.5)', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);
        $fileId = $this->post("/api/app/v1/competitions/{$competition->public_id}/attachments", ['file' => pdfUpload(), 'kind' => 'document'], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.file.id');
        [, , $inviteeOwner] = Fixtures::invitee($competition);
        [, , $participantOwner] = Fixtures::participant($competition);

        Fixtures::signIn($participantOwner);
        $this->get("/api/app/v1/files/{$fileId}/download")->assertOk();

        Fixtures::signIn($inviteeOwner);
        $this->getJson("/api/app/v1/files/{$fileId}/download")->assertForbidden();
    });

    it('updates the title and order, and deletes only in draft or scheduled', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        $attachment = CompetitionAttachment::factory()->create(['competition_id' => $competition->id]);
        $live = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        $liveAttachment = CompetitionAttachment::factory()->create(['competition_id' => $live->id]);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}/attachments/{$attachment->public_id}", ['title' => 'كراسة الشروط', 'sort_order' => 5])
            ->assertOk()->assertJsonPath('data.title', 'كراسة الشروط')->assertJsonPath('data.sort_order', 5);

        $this->deleteJson("/api/app/v1/competitions/{$live->public_id}/attachments/{$liveAttachment->public_id}")
            ->assertStatus(409)->assertJsonPath('code', 'competition_not_editable');

        $this->deleteJson("/api/app/v1/competitions/{$competition->public_id}/attachments/{$attachment->public_id}")->assertNoContent();
        expect(CompetitionAttachment::query()->find($attachment->id))->toBeNull();
    });
});

describe('Q&A comments', function (): void {
    it('lets a participant ask, and the issuer reply; participants see aliases only', function (): void {
        Event::fake([CommentPosted::class]);
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        [$asker, $askerOrg, $askerOwner] = Fixtures::participant($competition);
        [, , $otherOwner] = Fixtures::participant($competition);

        Fixtures::signIn($askerOwner);
        $questionId = $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'هل يشمل السعر التوصيل؟'])
            ->assertCreated()
            ->assertJsonPath('data.author', ['kind' => 'me'])
            ->json('data.id');

        Fixtures::signIn($this->owner);
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'نعم.', 'parent_id' => $questionId])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $questionId)
            ->assertJsonPath('data.author.kind', 'issuer');

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.author', ['kind' => 'participant', 'alias_no' => $asker->alias_no, 'organization_name' => $askerOrg->name])
            ->assertJsonPath('data.0.replies.0.body', 'نعم.');

        Fixtures::signIn($otherOwner);
        $response = $this->getJson("/api/app/v1/competitions/{$competition->public_id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.author', ['kind' => 'participant', 'alias_no' => $asker->alias_no])
            ->assertJsonPath('data.0.replies.0.author.kind', 'issuer')
            ->assertJsonStructure(['meta' => ['pagination']]);

        expect(json_encode($response->json(), JSON_THROW_ON_ERROR))->not->toContain($askerOrg->name);
        Event::assertDispatchedTimes(CommentPosted::class, 2);
    });

    it('forbids a participant replying under another organization question', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        [$asker, , $askerOwner] = Fixtures::participant($competition);
        [, , $otherOwner] = Fixtures::participant($competition);
        $question = Comment::factory()->create([
            'competition_id' => $competition->id,
            'author_user_id' => $askerOwner->id,
            'author_organization_id' => $asker->organization_id,
            'author_participant_id' => $asker->id,
            'is_issuer' => false,
        ]);

        Fixtures::signIn($otherOwner);
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'وأنا أيضا', 'parent_id' => $question->public_id])
            ->assertForbidden();

        Fixtures::signIn($askerOwner);
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'توضيح', 'parent_id' => $question->public_id])
            ->assertCreated();
    });

    it('refuses invitees (403) and strangers (404)', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        [, , $inviteeOwner] = Fixtures::invitee($competition, status: InvitationStatus::Viewed);

        Fixtures::signIn($inviteeOwner);
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'سؤال'])->assertForbidden();
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/comments")->assertForbidden();

        [, $stranger] = Fixtures::supplier();
        Fixtures::signIn($stranger);
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/comments")->assertNotFound();
    });

    it('returns 409 comments_closed after the close, and validates the body', function (): void {
        $competition = Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'إعلان'])
            ->assertStatus(409)->assertJsonPath('code', 'comments_closed');
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['body']);
    });

    it('rejects a reply to a reply', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        $question = Comment::factory()->create(['competition_id' => $competition->id, 'author_user_id' => $this->owner->id, 'author_organization_id' => $this->org->id, 'is_issuer' => true]);
        $reply = Comment::factory()->create(['competition_id' => $competition->id, 'parent_id' => $question->id, 'author_user_id' => $this->owner->id, 'author_organization_id' => $this->org->id, 'is_issuer' => true]);
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/comments", ['body' => 'x', 'parent_id' => $reply->public_id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
    });
});
