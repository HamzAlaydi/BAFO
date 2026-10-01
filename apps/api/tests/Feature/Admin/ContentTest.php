<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Pages\Dashboard;
use App\Modules\Admin\Filament\Pages\ManageSettings;
use App\Modules\Admin\Filament\Resources\AccountDeletions\Pages\ListAccountDeletions;
use App\Modules\Admin\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Modules\Admin\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Modules\Admin\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Modules\Admin\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\CreateLegalDocument;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\EditLegalDocument;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\ListLegalDocuments;
use App\Modules\Admin\Filament\Widgets\LiveCompetitionsTable;
use App\Modules\Admin\Filament\Widgets\PlatformStatsOverview;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\ContactMessage;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Settings\AppSetting;
use App\Support\Settings\Settings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 content and operations pages: dashboard, legal documents, contact inbox, settings, audit
 * log and the account-deletion queue.
 */

beforeEach(function () {
    $this->admin = AdminPanel::signIn();
});

describe('dashboard', function () {
    it('counts the six §16 metrics', function () {
        Storage::fake('private');
        Competition::factory()->withoutFinalWindow()->live()->count(2)->create();
        $payment = Payment::factory()->succeeded()->create(['paid_at' => now(), 'total_minor' => 115_000]);
        Invoice::factory()->create(['payment_id' => $payment->id, 'einvoice_status' => EInvoiceStatus::Failed]);
        CompetitionSponsorship::factory()->create(['status' => SponsorshipStatus::Settled, 'unused_count' => 1]);

        Livewire::test(PlatformStatsOverview::class)
            ->assertSee(__('admin.dashboard.stats.live_competitions'))
            ->assertSee(__('admin.dashboard.stats.sponsorships_awaiting_voucher'))
            ->assertSee('1,150.00');

        Livewire::test(LiveCompetitionsTable::class)
            ->assertCanSeeTableRecords(Competition::query()->where('status', 'live')->get());

        $this->get('/admin')->assertOk();
        expect((new Dashboard)->getWidgets())->toBe([PlatformStatsOverview::class, LiveCompetitionsTable::class]);
    });
});

describe('legal documents', function () {
    it('creates a draft, edits it and publishes it through the Platform Actions', function () {
        Livewire::test(CreateLegalDocument::class)
            ->fillForm([
                'code' => LegalDocumentCode::Privacy->value,
                'locale' => 'en',
                'version' => '2026-11-01',
                'title' => 'Privacy policy',
                'body_markdown' => 'Draft text',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = LegalDocument::query()->where('version', '2026-11-01')->sole();
        expect($document->published_at)->toBeNull()
            ->and($document->created_by_admin_id)->toBe($this->admin->id);

        Livewire::test(EditLegalDocument::class, ['record' => $document->public_id])
            ->fillForm(['title' => 'Privacy policy (v2)'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditLegalDocument::class, ['record' => $document->public_id])
            ->callAction('publish', data: ['at' => null])
            ->assertHasNoActionErrors();

        $document->refresh();
        expect($document->title)->toBe('Privacy policy (v2)')
            ->and($document->published_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'legal_document.published')->value('actor_type')?->value)->toBe('admin');
    });

    it('refuses a duplicate version and keeps published versions read-only', function () {
        $published = LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2026-10-01')->create();

        Livewire::test(CreateLegalDocument::class)
            ->fillForm(['code' => 'terms', 'locale' => 'ar', 'version' => '2026-10-01', 'title' => 'x', 'body_markdown' => 'y'])
            ->call('create')
            ->assertHasFormErrors(['version' => 'unique']);

        Livewire::test(ListLegalDocuments::class)
            ->assertCanSeeTableRecords([$published])
            ->assertActionHidden(TestAction::make('edit')->table($published))
            ->assertActionHidden(TestAction::make('publish')->table($published));

        $this->get('/admin/legal-documents/'.$published->public_id.'/edit')->assertForbidden();
        $this->get('/admin/legal-documents/'.$published->public_id)->assertOk();
    });
});

describe('contact inbox', function () {
    it('marks a new message read when opened and changes its status', function () {
        $message = ContactMessage::factory()->create(['subject' => 'Partnership request']);

        Livewire::test(ListContactMessages::class)->assertCanSeeTableRecords([$message]);

        Livewire::test(ViewContactMessage::class, ['record' => $message->public_id])->assertSee('Partnership request');
        expect($message->refresh()->status)->toBe(ContactStatus::Read);

        Livewire::test(ViewContactMessage::class, ['record' => $message->public_id])
            ->callAction('changeStatus', data: ['status' => ContactStatus::Archived->value]);

        expect($message->refresh()->status)->toBe(ContactStatus::Archived)
            ->and($message->handled_by_admin_id)->toBe($this->admin->id);
    });
});

describe('settings', function () {
    it('lists every registered setting and saves only the changed ones through UpdateAppSetting', function () {
        Livewire::test(ManageSettings::class)
            ->assertSee('app.maintenance.enabled')
            ->assertSee('bidding.closing_soon_minutes')
            ->assertSchemaStateSet([
                'app__maintenance__enabled' => false,
                'competitions__max_participants' => 200,
                'sponsorship__pass_price_tender_minor' => '200.00',
            ], 'form')
            ->fillForm([
                'app__maintenance__enabled' => true,
                'app__maintenance__message' => ['ar' => 'صيانة مجدولة', 'en' => 'Scheduled maintenance'],
                'competitions__max_participants' => '150',
                'sponsorship__pass_price_tender_minor' => '250.00',
                'bidding__closing_soon_minutes' => ['15', '5'],
            ], 'form')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $settings = app(Settings::class);
        expect($settings->get('app.maintenance.enabled'))->toBeTrue()
            ->and($settings->get('app.maintenance.message'))->toBe(['ar' => 'صيانة مجدولة', 'en' => 'Scheduled maintenance'])
            ->and($settings->get('competitions.max_participants'))->toBe(150)
            ->and($settings->get('sponsorship.pass_price_tender_minor'))->toBe(25_000)
            ->and($settings->get('bidding.closing_soon_minutes'))->toBe([15, 5])
            ->and(AppSetting::query()->where('key', 'app.maintenance.enabled')->value('updated_by_admin_id'))->toBe($this->admin->id)
            ->and(AuditLog::query()->where('action', 'setting.updated')->count())->toBe(5);
    });

    it('is closed to operators', function () {
        AdminPanel::signIn(AdminPanel::operator());

        Livewire::test(ManageSettings::class)->assertForbidden();
    });
});

describe('audit log', function () {
    it('lists entries with filters and shows the changes', function () {
        AuditLogger::log('organization.verified', null, ['verified_at' => ['from' => null, 'to' => '2026-09-29']], ['note' => 'x'], Actor::forAdmin($this->admin));
        AuditLogger::log('user.signed_in', null, actor: Actor::system());
        $entry = AuditLog::query()->where('action', 'organization.verified')->sole();
        $other = AuditLog::query()->where('action', 'user.signed_in')->sole();

        Livewire::test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$entry, $other])
            ->filterTable('actor_type', 'admin')
            ->assertCanSeeTableRecords([$entry])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ViewAuditLog::class, ['record' => $entry->id])
            ->assertSee('verified_at.to')
            ->assertSee('2026-09-29');
    });
});

describe('account deletions', function () {
    it('shows the queue by status, pending first', function () {
        $pending = AccountDeletionRequest::factory()->create();
        $completed = AccountDeletionRequest::factory()->completed()->create();

        expect($pending->status)->toBe(DeletionStatus::Pending);

        Livewire::test(ListAccountDeletions::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$completed])
            ->set('activeTab', 'completed')
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$pending]);
    });
});
