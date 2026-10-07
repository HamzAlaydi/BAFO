<?php

declare(strict_types=1);

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Pages\Dashboard;
use App\Modules\Admin\Filament\Pages\ManageSettings;
use App\Modules\Admin\Filament\Resources\Admins\AdminAccountResource;
use App\Modules\Admin\Filament\Resources\ApiClients\ApiClientResource;
use App\Modules\Admin\Filament\Resources\AuditLogs\AuditLogResource;
use App\Modules\Admin\Filament\Resources\Categories\CategoryResource;
use App\Modules\Admin\Filament\Resources\CloseReasons\CloseReasonResource;
use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\AwardsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ExtensionsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\InvitationsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\OffersRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ParticipantsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\RejectionsRelationManager;
use App\Modules\Admin\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Modules\Admin\Filament\Resources\Coupons\CouponResource;
use App\Modules\Admin\Filament\Resources\Invoices\InvoiceResource;
use App\Modules\Admin\Filament\Resources\LegalDocuments\LegalDocumentResource;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Modules\Admin\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Modules\Admin\Filament\Resources\Payments\PaymentResource;
use App\Modules\Admin\Filament\Resources\Plans\PlanResource;
use App\Modules\Admin\Filament\Resources\Presets\CompetitionPresetResource;
use App\Modules\Admin\Filament\Resources\Regions\RegionResource;
use App\Modules\Admin\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Admin\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\SettingsForm;
use App\Modules\Admin\Filament\Widgets\LiveCompetitionsTable;
use App\Modules\Admin\Filament\Widgets\PlatformStatsOverview;
use App\Modules\Admin\Support\AdminLocale;
use App\Modules\Admin\Support\AdminScope;
use App\Modules\Admin\Support\PlatformMetrics;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Platform\Models\ContactMessage;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditLogger;
use App\Support\Features\FeatureFlags;
use App\Support\Features\ReleaseScope;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
|--------------------------------------------------------------------------
| Minimal ops panel (RELEASE_SCOPE.md §11)
|--------------------------------------------------------------------------
|
| Release scope `core` keeps the panel at its minimum: Dashboard (four counters and the live
| table), Organizations, Users, Competitions (Cancel and Force close), Subscriptions (list and
| grant) and Settings (essential groups). Everything else is hidden through the one check
| AdminScope::visible(): out of the navigation, and a direct URL answers 403. Scope `full`
| brings every page, action and column back unchanged (the rest of the Admin suite runs in full).
|
*/

beforeEach(fn () => Mail::fake());

/**
 * The sidebar for the signed-in admin as [group label => item labels] ('' = ungrouped).
 *
 * @return array<string, list<string>>
 */
function opsNavigation(): array
{
    // The navigation manager is scoped and mounts once; read it fresh for the current scope.
    app()->forgetScopedInstances();

    $navigation = [];

    /** @var NavigationGroup $group */
    foreach (Filament::getPanel('admin')->getNavigation() as $group) {
        $items = $group->getItems();
        $navigation[(string) $group->getLabel()] = array_values(array_map(
            static fn (NavigationItem $item): string => $item->getLabel(),
            is_array($items) ? $items : $items->toArray(),
        ));
    }

    return $navigation;
}

/**
 * @return array{ownerRecord: Competition, pageClass: class-string}
 */
function opsRelationContext(Competition $competition): array
{
    return ['ownerRecord' => $competition, 'pageClass' => ViewCompetition::class];
}

/**
 * Record pages of the hidden resources, one per resource.
 *
 * @return array<string, array{0: class-string<AdminResource>, 1: string, 2: Closure(): Model}>
 */
function opsHiddenRecordPages(): array
{
    return [
        'plan' => [PlanResource::class, 'edit', static fn (): Model => Plan::factory()->create()],
        'coupon' => [CouponResource::class, 'edit', static fn (): Model => Coupon::factory()->create()],
        'payment' => [PaymentResource::class, 'view', static fn (): Model => Payment::factory()->create()],
        'invoice' => [InvoiceResource::class, 'view', static fn (): Model => Invoice::factory()->create()],
        'api client' => [ApiClientResource::class, 'view', static fn (): Model => ApiClient::factory()->create()],
        'webhook endpoint' => [WebhookEndpointResource::class, 'view', static fn (): Model => WebhookEndpoint::factory()->create()],
        'region' => [RegionResource::class, 'edit', static fn (): Model => Region::factory()->create()],
        'category' => [CategoryResource::class, 'edit', static fn (): Model => Category::factory()->create()],
        'close reason' => [CloseReasonResource::class, 'edit', static fn (): Model => CloseReason::factory()->create()],
        'preset' => [CompetitionPresetResource::class, 'edit', static fn (): Model => CompetitionPreset::factory()->create()],
        'legal document' => [LegalDocumentResource::class, 'view', static fn (): Model => LegalDocument::factory()->create()],
        'contact message' => [ContactMessageResource::class, 'view', static fn (): Model => ContactMessage::factory()->create()],
        'admin' => [AdminAccountResource::class, 'edit', static fn (): Model => AdminPanel::operator()],
        'audit entry' => [AuditLogResource::class, 'view', static function (): Model {
            AuditLogger::log('organization.updated', Organization::factory()->create());

            return AuditLog::query()->latest('id')->firstOrFail();
        }],
    ];
}

describe('the central check', function () {
    it('shows the core surfaces in both scopes and the rest in full only', function () {
        $this->releaseScope(ReleaseScope::Core);

        expect(array_map(static fn (OpsSurface $surface): string => $surface->value, OpsSurface::core()))
            ->toBe(['dashboard', 'organizations', 'users', 'competitions', 'subscriptions', 'settings']);

        foreach (OpsSurface::cases() as $surface) {
            expect(AdminScope::visible($surface))->toBe($surface->inCore(), $surface->value);
        }

        $this->releaseScope(ReleaseScope::Full);

        foreach (OpsSurface::cases() as $surface) {
            expect(AdminScope::visible($surface))->toBeTrue($surface->value);
        }
    });

    it('names a surface on every panel resource', function () {
        $resources = Filament::getPanel('admin')->getResources();

        expect($resources)->toHaveCount(21);

        foreach ($resources as $resource) {
            expect(is_subclass_of($resource, AdminResource::class))->toBeTrue($resource)
                ->and($resource::opsSurface())->toBeInstanceOf(OpsSurface::class);
        }

        expect(collect($resources)->filter(static fn (string $resource): bool => $resource::opsSurface()->inCore())->values()->all())
            ->toEqualCanonicalizing([
                OrganizationResource::class,
                UserResource::class,
                CompetitionResource::class,
                SubscriptionResource::class,
            ]);
    });
});

describe('navigation', function () {
    it('keeps the core sidebar to five pages in four groups', function () {
        AdminPanel::signIn();
        $this->releaseScope(ReleaseScope::Core);

        expect(opsNavigation())->toBe([
            '' => [Dashboard::getNavigationLabel()],
            AdminNavigationGroup::Customers->label() => [OrganizationResource::getNavigationLabel(), UserResource::getNavigationLabel()],
            AdminNavigationGroup::Competitions->label() => [CompetitionResource::getNavigationLabel()],
            AdminNavigationGroup::Billing->label() => [SubscriptionResource::getNavigationLabel()],
            AdminNavigationGroup::Content->label() => [ManageSettings::getNavigationLabel()],
        ]);
    });

    it('drops the settings group for an operator in core (no empty group)', function () {
        AdminPanel::signIn(AdminPanel::operator());
        $this->releaseScope(ReleaseScope::Core);

        expect(array_keys(opsNavigation()))->toBe([
            '',
            AdminNavigationGroup::Customers->label(),
            AdminNavigationGroup::Competitions->label(),
            AdminNavigationGroup::Billing->label(),
        ]);
    });

    it('brings every group and page back in full', function () {
        AdminPanel::signIn();
        $this->releaseScope(ReleaseScope::Core);
        opsNavigation();

        $this->releaseScope(ReleaseScope::Full);
        $navigation = opsNavigation();

        expect(array_keys($navigation))->toBe(['', ...array_map(
            static fn (AdminNavigationGroup $group): string => $group->label(),
            AdminNavigationGroup::cases(),
        )])
            ->and(array_map('count', $navigation))->toBe([
                '' => 1,
                AdminNavigationGroup::Customers->label() => 3,
                AdminNavigationGroup::Competitions->label() => 1,
                AdminNavigationGroup::Billing->label() => 7,
                AdminNavigationGroup::Integrations->label() => 2,
                AdminNavigationGroup::Lookups->label() => 4,
                AdminNavigationGroup::Content->label() => 3,
                AdminNavigationGroup::System->label() => 2,
            ]);
    });

    it('renders the core sidebar in Arabic (RTL) and English', function (string $locale, array $shown, array $hidden) {
        $this->releaseScope(ReleaseScope::Core);

        $response = $this->actingAs(AdminPanel::superAdmin(), 'admin')
            ->withSession([AdminLocale::SESSION_KEY => $locale])
            ->get('/admin/organizations')
            ->assertOk()
            ->assertSee($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', false);

        foreach ($shown as $label) {
            $response->assertSee($label);
        }

        foreach ($hidden as $label) {
            $response->assertDontSee($label);
        }
    })->with([
        'Arabic' => ['ar', ['العملاء', 'الفوترة', 'المنشآت', 'الاشتراكات'], ['سجل التدقيق', 'القوائم المرجعية', 'التكامل']],
        'English' => ['en', ['Customers', 'Billing', 'Organisations', 'Subscriptions'], ['Audit log', 'Lookups', 'Integrations']],
    ]);
});

describe('hidden pages in core', function () {
    beforeEach(function () {
        $this->releaseScope(ReleaseScope::Core);
    });

    it('leaves the page out of the sidebar and answers 403 on its URL', function (string $url) {
        $admin = AdminPanel::superAdmin();

        $this->actingAs($admin, 'admin')->get('/admin')
            ->assertOk()
            ->assertDontSee('href="'.url($url).'"', false);

        $this->actingAs($admin, 'admin')->get($url)->assertForbidden();
    })->with(AdminPanel::FULL_ONLY_PAGES);

    it('answers 403 on a record page of a hidden resource', function (string $resource, string $page, Closure $record) {
        /** @var class-string<AdminResource> $resource */
        $url = $resource::getUrl($page, ['record' => $record()]);

        $this->actingAs(AdminPanel::superAdmin(), 'admin')->get($url)->assertForbidden();
    })->with(opsHiddenRecordPages());

    it('keeps the hidden resources out of global search', function () {
        AdminPanel::signIn();

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            expect($resource::canAccess())->toBe($resource::opsSurface()->inCore(), $resource);

            if (! $resource::opsSurface()->inCore()) {
                expect($resource::canGloballySearch())->toBeFalse($resource);
            }
        }
    });

    it('brings the same pages back when the scope is full again', function (string $url) {
        $admin = AdminPanel::superAdmin();
        $this->actingAs($admin, 'admin')->get($url)->assertForbidden();

        $this->releaseScope(ReleaseScope::Full);

        $this->actingAs($admin, 'admin')->get($url)->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin')->assertSee('href="'.url(preg_replace('#/create$#', '', $url)).'"', false);
    })->with(AdminPanel::FULL_ONLY_PAGES);

    it('brings the record pages back in full', function (string $resource, string $page, Closure $record) {
        /** @var class-string<AdminResource> $resource */
        $url = $resource::getUrl($page, ['record' => $record()]);
        $this->releaseScope(ReleaseScope::Full);

        $this->actingAs(AdminPanel::superAdmin(), 'admin')->get($url)->assertOk();
    })->with(opsHiddenRecordPages());
});

describe('core pages', function () {
    beforeEach(function () {
        $this->releaseScope(ReleaseScope::Core);
    });

    it('renders every core page for a super admin', function (string $url) {
        $this->actingAs(AdminPanel::superAdmin(), 'admin')->get($url)->assertOk();
    })->with(AdminPanel::CORE_PAGES);

    it('renders the operations pages for an operator and keeps settings super-admin only', function () {
        $operator = AdminPanel::operator();

        foreach (array_diff(AdminPanel::CORE_PAGES, ['/admin/settings']) as $url) {
            $this->actingAs($operator, 'admin')->get($url)->assertOk();
        }

        $this->actingAs($operator, 'admin')->get('/admin/settings')->assertForbidden();
    });

    it('renders the record pages of the core resources', function () {
        $organization = Organization::factory()->create();
        $user = User::factory()->withMembership($organization, OrgRole::Owner)->create();
        $competition = Competition::factory()->withoutFinalWindow()->live()->create(['organization_id' => $organization->id]);
        $admin = AdminPanel::superAdmin();

        $this->actingAs($admin, 'admin')->get(OrganizationResource::getUrl('view', ['record' => $organization]))->assertOk();
        $this->actingAs($admin, 'admin')->get(UserResource::getUrl('view', ['record' => $user]))->assertOk();
        $this->actingAs($admin, 'admin')->get(CompetitionResource::getUrl('view', ['record' => $competition]))->assertOk();
    });
});

describe('dashboard', function () {
    beforeEach(function () {
        AdminPanel::signIn();
    });

    it('shows the four essential counters in core', function () {
        $this->releaseScope(ReleaseScope::Core);

        Livewire::test(PlatformStatsOverview::class)
            ->assertSee(__('admin.dashboard.stats.organizations'))
            ->assertSee(__('admin.dashboard.stats.live_competitions'))
            ->assertSee(__('admin.dashboard.stats.competitions_this_month'))
            ->assertSee(__('admin.dashboard.stats.active_subscriptions'))
            ->assertDontSee(__('admin.dashboard.stats.payments_today'))
            ->assertDontSee(__('admin.dashboard.stats.failed_einvoices'))
            ->assertDontSee(__('admin.dashboard.stats.sponsorships_awaiting_voucher'));

        expect((new Dashboard)->getWidgets())->toBe([PlatformStatsOverview::class, LiveCompetitionsTable::class]);

        $this->get('/admin')->assertOk()->assertDontSee('href="'.url('/admin/payments').'"', false);
    });

    it('adds the billing counters in full', function () {
        Livewire::test(PlatformStatsOverview::class)
            ->assertSee(__('admin.dashboard.stats.competitions_this_month'))
            ->assertSee(__('admin.dashboard.stats.payments_today'))
            ->assertSee(__('admin.dashboard.stats.failed_einvoices'))
            ->assertSee(__('admin.dashboard.stats.sponsorships_awaiting_voucher'));
    });

    it('counts the competitions published since the start of the Riyadh month', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00', 'Asia/Riyadh'));
        $startOfMonth = CarbonImmutable::parse('2026-10-01 00:00', 'Asia/Riyadh');

        Competition::factory()->create(['published_at' => $startOfMonth->addMinute()]);
        Competition::factory()->create(['published_at' => $startOfMonth->addDays(10)]);
        Competition::factory()->create(['published_at' => $startOfMonth->subMinute()]);
        Competition::factory()->create(['published_at' => null]);

        expect(app(PlatformMetrics::class)->competitionsThisMonth())->toBe(2);
    });
});

describe('competition view in core', function () {
    beforeEach(function () {
        $this->admin = AdminPanel::signIn(AdminPanel::operator());
        $this->releaseScope(ReleaseScope::Core);
    });

    it('offers cancel and force close, not extend', function () {
        $competition = Competition::factory()->withoutFinalWindow()->live()->create();

        Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
            ->assertActionHidden('extend')
            ->assertActionVisible('forceClose')
            ->assertActionVisible('cancel')
            ->callAction('forceClose', data: ['reason' => 'Court order received'])
            ->assertHasNoActionErrors();

        expect($competition->refresh()->status)->toBe(CompetitionStatus::Closed);
    });

    it('cancels a scheduled competition', function () {
        $competition = Competition::factory()->scheduled()->create();
        $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create();

        Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
            ->callAction('cancel', data: ['reason_id' => $reason->id])
            ->assertHasNoActionErrors();

        expect($competition->refresh()->status)->toBe(CompetitionStatus::Cancelled);
    });

    it('shows invitations, participants, offers and awards only', function () {
        $competition = Competition::factory()->closed()->create();

        expect(InvitationsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeTrue()
            ->and(ParticipantsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeTrue()
            ->and(OffersRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeTrue()
            ->and(AwardsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeTrue()
            ->and(ExtensionsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeFalse()
            ->and(RejectionsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeFalse();

        $this->releaseScope(ReleaseScope::Full);

        expect(ExtensionsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeTrue()
            ->and(RejectionsRelationManager::canViewForRecord($competition, ViewCompetition::class))->toBeTrue();
    });

    it('hides revoke, grant pass and the sponsored columns on invitations', function () {
        $competition = Competition::factory()->scheduled()->create();
        CompetitionSponsorship::factory()->selected()->create(['competition_id' => $competition->id, 'organization_id' => $competition->organization_id]);
        $invitation = Invitation::factory()->sent()->create(['competition_id' => $competition->id]);

        Livewire::test(InvitationsRelationManager::class, opsRelationContext($competition))
            ->assertCanSeeTableRecords([$invitation])
            ->assertActionHidden(TestAction::make('revoke')->table($invitation))
            ->assertActionHidden(TestAction::make('grantPass')->table($invitation))
            ->assertTableColumnHidden('sponsored_requested')
            ->assertTableColumnHidden('has_live_pass');

        $this->releaseScope(ReleaseScope::Full);

        Livewire::test(InvitationsRelationManager::class, opsRelationContext($competition))
            ->assertActionVisible(TestAction::make('revoke')->table($invitation))
            ->assertActionVisible(TestAction::make('grantPass')->table($invitation))
            ->assertTableColumnVisible('sponsored_requested')
            ->assertTableColumnVisible('has_live_pass');
    });

    it('hides void offer, and the void columns unless an offer is voided already', function () {
        $competition = Competition::factory()->withoutFinalWindow()->live()->create();
        $offer = Offer::factory()->create(['competition_id' => $competition->id]);

        Livewire::test(OffersRelationManager::class, opsRelationContext($competition))
            ->assertCanSeeTableRecords([$offer])
            ->assertActionHidden(TestAction::make('void')->table($offer))
            ->assertTableColumnHidden('voided')
            ->assertTableColumnHidden('void_reason');

        $voided = Offer::factory()->create(['competition_id' => $competition->id, 'participant_id' => $offer->participant_id]);
        OfferVoid::factory()->create(['offer_id' => $voided->id]);

        Livewire::test(OffersRelationManager::class, opsRelationContext($competition))
            ->assertActionHidden(TestAction::make('void')->table($offer))
            ->assertTableColumnVisible('voided')
            ->assertTableColumnVisible('void_reason');

        $this->releaseScope(ReleaseScope::Full);

        Livewire::test(OffersRelationManager::class, opsRelationContext($competition))
            ->assertActionVisible(TestAction::make('void')->table($offer));
    });

    it('hides the ERP sync column of awards', function () {
        $competition = Competition::factory()->closed()->create();

        Livewire::test(AwardsRelationManager::class, opsRelationContext($competition))->assertTableColumnHidden('erp_sync_status');

        $this->releaseScope(ReleaseScope::Full);

        Livewire::test(AwardsRelationManager::class, opsRelationContext($competition))->assertTableColumnVisible('erp_sync_status');
    });

    it('hides the BAFO round, final window and sponsorship rows unless the competition uses them', function () {
        $plain = Competition::factory()->withoutFinalWindow()->live()->create(['bafo_round_enabled' => false]);

        Livewire::test(ViewCompetition::class, ['record' => $plain->public_id])
            ->assertSchemaComponentHidden('bafo_round', 'infolist')
            ->assertSchemaComponentHidden('final_window_minutes', 'infolist')
            ->assertSchemaComponentHidden('final_window_starts_at', 'infolist')
            ->assertSchemaComponentHidden('sponsorship', 'infolist')
            ->assertSchemaComponentVisible('auto_extend', 'infolist')
            ->assertSchemaComponentVisible('effective_close_at', 'infolist');

        $legacy = Competition::factory()->live()->create(['bafo_round_enabled' => true, 'bafo_duration_minutes' => 60]);
        CompetitionSponsorship::factory()->selected()->create(['competition_id' => $legacy->id, 'organization_id' => $legacy->organization_id]);
        expect($legacy->final_window_minutes)->not->toBeNull();

        Livewire::test(ViewCompetition::class, ['record' => $legacy->public_id])
            ->assertSchemaComponentVisible('bafo_round', 'infolist')
            ->assertSchemaComponentVisible('final_window_minutes', 'infolist')
            ->assertSchemaComponentVisible('final_window_starts_at', 'infolist')
            ->assertSchemaComponentVisible('sponsorship', 'infolist');

        $this->releaseScope(ReleaseScope::Full);

        Livewire::test(ViewCompetition::class, ['record' => $plain->public_id])
            ->assertActionVisible('extend')
            ->assertSchemaComponentVisible('bafo_round', 'infolist')
            ->assertSchemaComponentVisible('final_window_minutes', 'infolist')
            ->assertSchemaComponentVisible('sponsorship', 'infolist');
    });
});

describe('organizations and users in core', function () {
    beforeEach(function () {
        AdminPanel::signIn(AdminPanel::operator());
        $this->releaseScope(ReleaseScope::Core);
        $this->organization = Organization::factory()->create(['api_enabled' => true, 'auction_enabled' => true, 'sponsorship_enabled' => true]);
    });

    it('lists and views without the API and sponsorship switches', function () {
        Livewire::test(ListOrganizations::class)
            ->assertCanSeeTableRecords([$this->organization])
            ->assertTableColumnVisible('auction_enabled')
            ->assertTableColumnHidden('api_enabled')
            ->assertTableColumnHidden('sponsorship_enabled')
            ->assertTableFilterVisible('auction_enabled')
            ->assertTableFilterHidden('api_enabled')
            ->assertTableFilterHidden('sponsorship_enabled');

        Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
            ->assertSchemaComponentVisible('auction_enabled', 'infolist')
            ->assertSchemaComponentHidden('api_enabled', 'infolist')
            ->assertSchemaComponentHidden('sponsorship_enabled', 'infolist');
    });

    it('saves only the auction switch and leaves the hidden switches untouched', function () {
        Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
            ->callAction('features', data: ['auction_enabled' => false, 'api_enabled' => false, 'sponsorship_enabled' => false])
            ->assertHasNoActionErrors();

        $this->organization->refresh();
        expect($this->organization->auction_enabled)->toBeFalse()
            ->and($this->organization->api_enabled)->toBeTrue()
            ->and($this->organization->sponsorship_enabled)->toBeTrue();

        $this->releaseScope(ReleaseScope::Full);

        Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
            ->callAction('features', data: ['auction_enabled' => true, 'api_enabled' => false, 'sponsorship_enabled' => false])
            ->assertHasNoActionErrors();

        $this->organization->refresh();
        expect($this->organization->auction_enabled)->toBeTrue()
            ->and($this->organization->api_enabled)->toBeFalse()
            ->and($this->organization->sponsorship_enabled)->toBeFalse();
    });

    it('suspends and reactivates', function () {
        Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
            ->callAction('suspend', data: ['reason' => 'Fraud review'])
            ->assertHasNoActionErrors();

        expect($this->organization->refresh()->status->value)->toBe('suspended');

        Livewire::test(ViewOrganization::class, ['record' => $this->organization->public_id])
            ->callAction('unsuspend')
            ->assertHasNoActionErrors();

        expect($this->organization->refresh()->status->value)->toBe('active');
    });

    it('hides the team permission columns and keeps deactivate / reactivate', function () {
        $user = User::factory()->withMembership($this->organization, OrgRole::Owner)->create();

        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->organization, 'pageClass' => ViewOrganization::class])
            ->assertTableColumnHidden('can_award')
            ->assertTableColumnHidden('can_purchase');

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertSchemaComponentHidden('membership.can_award', 'infolist')
            ->assertActionVisible('deactivateMembership')
            ->callAction('deactivateMembership')
            ->assertHasNoActionErrors();

        expect($user->membership()->firstOrFail()->status)->toBe(MembershipStatus::Inactive);

        $this->releaseScope(ReleaseScope::Full);

        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->organization, 'pageClass' => ViewOrganization::class])
            ->assertTableColumnVisible('can_award')
            ->assertTableColumnVisible('can_purchase');
    });

    it('lists subscriptions and grants one', function () {
        $plan = Plan::factory()->create(['is_active' => true]);

        Livewire::test(ListSubscriptions::class)
            ->callAction(TestAction::make('grantSubscription')->table(), data: [
                'organization_id' => $this->organization->id,
                'plan_id' => $plan->id,
                'seats' => 3,
                'starts_at' => CarbonImmutable::now()->toDateTimeString(),
                'ends_at' => CarbonImmutable::now()->addMonth()->toDateTimeString(),
                'reason' => 'Pilot customer',
            ])
            ->assertHasNoActionErrors();

        expect($this->organization->subscriptions()->where('plan_id', $plan->id)->exists())->toBeTrue();
    });
});

describe('settings in core', function () {
    beforeEach(function () {
        $this->admin = AdminPanel::signIn();
        $this->releaseScope(ReleaseScope::Core);
    });

    it('shows the essential groups only, with the release scope select', function () {
        Livewire::test(ManageSettings::class)
            ->assertFormFieldExists('platform__release_scope', 'form')
            ->assertFormFieldExists('app__maintenance__enabled', 'form')
            ->assertFormFieldExists('app__min_version__ios', 'form')
            ->assertFormFieldExists('app__support.email', 'form')
            ->assertFormFieldDoesNotExist('billing__trial_days', 'form')
            ->assertFormFieldDoesNotExist('bidding__max_amount_minor', 'form')
            ->assertFormFieldDoesNotExist('sponsorship__enabled', 'form')
            ->assertFormFieldDoesNotExist('competitions__max_participants', 'form')
            ->assertSee(__('admin.settings.core_hint'));
    });

    it('saves a visible key without touching the hidden ones', function () {
        $settings = app(Settings::class);
        $settings->set('billing.trial_days', 14, null);

        Livewire::test(ManageSettings::class)
            ->fillForm(['app__maintenance__enabled' => true], 'form')
            ->call('save')
            ->assertHasNoFormErrors();

        expect($settings->get('app.maintenance.enabled'))->toBeTrue()
            ->and($settings->get('billing.trial_days'))->toBe(14)
            ->and(array_keys(AuditLog::query()->where('action', 'setting.updated')->sole()->changes ?? []))->toBe(['app.maintenance.enabled']);
    });

    it('switches to full from the core form and then shows every group', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm(['platform__release_scope' => 'full'], 'form')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Full)
            ->and(app(SettingsForm::class)->shown('billing.trial_days'))->toBeTrue();

        Livewire::test(ManageSettings::class)
            ->assertFormFieldExists('billing__trial_days', 'form')
            ->assertFormFieldExists('sponsorship__enabled', 'form')
            ->assertDontSee(__('admin.settings.core_hint'));
    });
});

it('keeps the profile page reachable in core', function () {
    $this->releaseScope(ReleaseScope::Core);

    $this->actingAs(AdminPanel::superAdmin(), 'admin')->get('/admin/profile')->assertOk();
    expect(is_subclass_of(ManageSettings::class, Page::class))->toBeTrue();
});
