<?php

declare(strict_types=1);

use App\Modules\Platform\Actions\ChangeContactMessageStatus;
use App\Modules\Platform\Actions\PublishLegalDocument;
use App\Modules\Platform\Actions\SaveLegalDocumentDraft;
use App\Modules\Platform\Actions\UpdateAppSetting;
use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\ContactMessage;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Exceptions\ApiException;
use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Model;

function platformAdminActor(int $id = 2): Actor
{
    $admin = new class extends Model {};
    $admin->forceFill(['id' => $id, 'name' => 'Ops Admin']);

    return Actor::forAdmin($admin);
}

it('creates and edits a legal document draft', function () {
    $draft = app(SaveLegalDocumentDraft::class)->handle(null, [
        'code' => LegalDocumentCode::Terms,
        'locale' => 'ar',
        'version' => '2026-12-01',
        'title' => 'الشروط والأحكام',
        'body_markdown' => 'نص',
    ], platformAdminActor());

    expect($draft->published_at)->toBeNull()
        ->and($draft->created_by_admin_id)->toBe(2);

    app(SaveLegalDocumentDraft::class)->handle($draft, ['title' => 'الشروط والأحكام المحدثة'], platformAdminActor());

    expect($draft->fresh()?->title)->toBe('الشروط والأحكام المحدثة')
        ->and(AuditLog::query()->orderBy('id')->pluck('action')->all())->toBe(['legal_document.created', 'legal_document.updated'])
        ->and(AuditLog::query()->latest('id')->first()?->changes)->toEqual(['title' => ['from' => 'الشروط والأحكام', 'to' => 'الشروط والأحكام المحدثة']])
        ->and(AuditLog::query()->first()?->actor_type)->toBe(ActorType::Admin);
});

it('publishes a draft and serves it', function () {
    $this->freezeSecond();
    $draft = LegalDocument::factory()->forCode(LegalDocumentCode::Privacy, 'ar', '2026-12-01')->draft()->create();

    $published = app(PublishLegalDocument::class)->handle($draft, platformAdminActor());

    expect($published->published_at?->equalTo(now()))->toBeTrue()
        ->and(AuditLog::query()->sole()->meta)->toEqual(['code' => 'privacy', 'locale' => 'ar', 'version' => '2026-12-01']);

    $this->getJson('/api/app/v1/legal/privacy')->assertOk()->assertJsonPath('data.version', '2026-12-01');
});

it('never changes a published version', function () {
    $published = LegalDocument::factory()->create();

    expect(fn () => app(PublishLegalDocument::class)->handle($published, platformAdminActor()))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invalid_state_transition')->and($e->status)->toBe(409));

    expect(fn () => app(SaveLegalDocumentDraft::class)->handle($published, ['title' => 'x'], platformAdminActor()))
        ->toThrow(ApiException::class);
});

it('changes the status of a contact message and records the handler', function () {
    $message = ContactMessage::factory()->create();

    app(ChangeContactMessageStatus::class)->handle($message, ContactStatus::Read, platformAdminActor(5));

    expect($message->fresh())
        ->status->toBe(ContactStatus::Read)
        ->handled_by_admin_id->toBe(5)
        ->and(AuditLog::query()->sole()->changes)->toEqual(['status' => ['from' => 'new', 'to' => 'read']]);
});

it('updates a registered setting and audits the change', function () {
    app(UpdateAppSetting::class)->handle('app.min_version.android', '1.5.0', platformAdminActor(3));

    $entry = AuditLog::query()->sole();

    expect(app(Settings::class)->get('app.min_version.android'))->toBe('1.5.0')
        ->and($entry->action)->toBe('setting.updated')
        ->and($entry->changes)->toEqual(['app.min_version.android' => ['from' => '1.0.0', 'to' => '1.5.0']])
        ->and($entry->actor_id)->toBe(3);
});

it('refuses unknown settings', function () {
    app(UpdateAppSetting::class)->handle('made.up', true, platformAdminActor());
})->throws(InvalidArgumentException::class);
