<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Pages\ManageSettings;
use App\Modules\Admin\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Modules\Admin\Filament\Resources\Presets\Pages\CreateCompetitionPreset;
use App\Modules\Admin\Filament\Resources\Presets\Pages\ListCompetitionPresets;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Enums\PresetTier;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Support\Audit\AuditLog;
use App\Support\Features\FeatureFlags;
use App\Support\Features\ReleaseScope;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
|--------------------------------------------------------------------------
| Release scope: admin (RELEASE_SCOPE.md §1.1, §1.6, §2.2)
|--------------------------------------------------------------------------
|
| The settings page renders `platform.release_scope` as a required select of core / full and
| saves it through UpdateAppSetting; the admin is never gated, so Extend and Cancel keep working
| in `core`; presets carry the tier field.
|
*/

beforeEach(fn () => Mail::fake());

describe('settings page', function () {
    beforeEach(function () {
        $this->admin = AdminPanel::signIn();
    });

    it('renders the release scope as a required select of core and full', function () {
        Livewire::test(ManageSettings::class)
            ->assertSee('platform.release_scope')
            ->assertFormFieldExists('platform__release_scope', 'form', function (Select $field): bool {
                return $field->isRequired()
                    && $field->getOptions() === ['core' => 'أساسي (المناقصات والمزايدات)', 'full' => 'كامل (جميع المزايا)'];
            })
            ->assertSchemaStateSet(['platform__release_scope' => 'full'], 'form');
    });

    it('shows the stored scope and saves both values, audited', function () {
        $this->releaseScope(ReleaseScope::Core);

        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['platform__release_scope' => 'core'], 'form')
            ->fillForm(['platform__release_scope' => 'full'], 'form')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Full);

        Livewire::test(ManageSettings::class)
            ->fillForm(['platform__release_scope' => 'core'], 'form')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Core)
            ->and(AuditLog::query()->where('action', 'setting.updated')->count())->toBe(2);

        $this->getJson('/api/app/v1/app-config')
            ->assertJsonPath('data.features.release_scope', 'core')
            ->assertJsonPath('data.features.flags.bafo_round', false);
    });

    it('rejects any other value and keeps the stored scope', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm(['platform__release_scope' => 'everything'], 'form')
            ->call('save')
            ->assertHasFormErrors(['platform__release_scope' => 'in']);

        expect(app(FeatureFlags::class)->scope())->toBe(ReleaseScope::Full)
            ->and(AuditLog::query()->where('action', 'setting.updated')->count())->toBe(0);
    });
});

describe('admin actions in core', function () {
    beforeEach(function () {
        $this->admin = AdminPanel::signIn(AdminPanel::operator());
        $this->releaseScope(ReleaseScope::Core);
    });

    it('extends a live competition although extend_competition is hidden from the apps', function () {
        $competition = Competition::factory()->withoutFinalWindow()->live()->create();
        $newClose = $competition->effective_close_at->addHour()->startOfMinute();

        Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
            ->callAction('extend', data: ['new_close_at' => $newClose->setTimezone('Asia/Riyadh')->toDateTimeString(), 'reason' => 'Supplier portal outage'])
            ->assertHasNoActionErrors();

        expect(CompetitionExtension::query()->where('competition_id', $competition->id)->sole()->kind)->toBe(ExtensionKind::Admin)
            ->and($competition->refresh()->effective_close_at->equalTo($newClose))->toBeTrue();
    });

    it('cancels a sealed competition created in full', function () {
        $competition = Competition::factory()->sealed()->scheduled()->create();
        $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create();

        $this->get('/admin/competitions/'.$competition->public_id)->assertOk();

        Livewire::test(ViewCompetition::class, ['record' => $competition->public_id])
            ->callAction('cancel', data: ['reason_id' => $reason->id])
            ->assertHasNoActionErrors();

        expect($competition->refresh()->status)->toBe(CompetitionStatus::Cancelled);
    });
});

describe('preset tier', function () {
    beforeEach(function () {
        AdminPanel::signIn();
    });

    it('creates a preset with a tier and lists it', function () {
        Livewire::test(CreateCompetitionPreset::class)
            ->assertFormFieldExists('tier', 'form', fn (Select $field): bool => array_keys($field->getOptions()) === ['simple', 'standard', 'protected'])
            ->fillForm([
                'code' => 'quick_simple',
                'sort_order' => 12,
                'name' => ['ar' => 'سريعة', 'en' => 'Quick'],
                'description' => ['ar' => 'قالب قصير', 'en' => 'A short preset'],
                'direction' => 'tender',
                'format' => 'live',
                'tier' => 'simple',
                'is_active' => true,
                'rules' => [
                    'amount_granularity_minor' => '100',
                    'must_beat' => 'own',
                    'rank_visibility' => 'leading_flag',
                    'result_publication' => 'outcome_only',
                    'show_prices' => false,
                    'min_participants' => 1,
                    'auto_extend' => ['enabled' => false],
                    'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $preset = CompetitionPreset::query()->where('code', 'quick_simple')->sole();

        expect($preset->tier)->toBe(PresetTier::Simple);

        Livewire::test(ListCompetitionPresets::class)->assertCanSeeTableRecords([$preset]);
    });

    it('leaves the tier empty for an untiered preset', function () {
        $preset = CompetitionPreset::factory()->create(['code' => 'legacy_template']);

        expect($preset->refresh()->tier)->toBeNull();

        Livewire::test(ListCompetitionPresets::class)->assertCanSeeTableRecords([$preset]);
    });
});
