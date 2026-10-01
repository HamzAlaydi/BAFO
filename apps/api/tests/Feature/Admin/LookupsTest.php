<?php

declare(strict_types=1);

use App\Modules\Admin\Actions\DeleteReferenceRecord;
use App\Modules\Admin\Filament\Resources\Categories\Pages\CreateCategory;
use App\Modules\Admin\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Admin\Filament\Resources\CloseReasons\Pages\CreateCloseReason;
use App\Modules\Admin\Filament\Resources\CloseReasons\Pages\EditCloseReason;
use App\Modules\Admin\Filament\Resources\Presets\Pages\CreateCompetitionPreset;
use App\Modules\Admin\Filament\Resources\Presets\Pages\EditCompetitionPreset;
use App\Modules\Admin\Filament\Resources\Regions\Pages\CreateRegion;
use App\Modules\Admin\Filament\Resources\Regions\Pages\EditRegion;
use App\Modules\Admin\Filament\Resources\Regions\Pages\ListRegions;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 Lookups: regions, categories (auction_allowed), close reasons and presets. CRUD with the code
 * immutable after create; a record still in use cannot be deleted. Every write is audited.
 */

beforeEach(function () {
    $this->admin = AdminPanel::signIn(AdminPanel::operator());
});

it('creates, edits and deletes a region', function () {
    Livewire::test(CreateRegion::class)
        ->fillForm(['code' => 'NEW', 'sort_order' => 20, 'name' => ['ar' => 'منطقة جديدة', 'en' => 'New region'], 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $region = Region::query()->where('code', 'NEW')->sole();

    Livewire::test(EditRegion::class, ['record' => $region->public_id])
        ->assertFormFieldIsDisabled('code')
        ->fillForm(['name' => ['ar' => 'منطقة معدلة', 'en' => 'Renamed region'], 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($region->refresh()->name)->toBe(['ar' => 'منطقة معدلة', 'en' => 'Renamed region'])
        ->and($region->is_active)->toBeFalse()
        ->and($region->code)->toBe('NEW');

    Livewire::test(EditRegion::class, ['record' => $region->public_id])->callAction('delete');

    expect(Region::query()->whereKey($region->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->whereIn('action', ['region.created', 'region.updated', 'region.deleted'])->pluck('actor_type')->map->value->unique()->all())->toBe(['admin']);
});

it('validates the region code', function () {
    Region::factory()->create(['code' => 'RIY']);

    Livewire::test(CreateRegion::class)
        ->fillForm(['code' => 'RIY', 'name' => ['ar' => 'الرياض', 'en' => ''], 'sort_order' => 1])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique', 'name.en' => 'required']);
});

it('keeps a region in use and refuses to delete it', function () {
    $region = Region::factory()->create();
    Organization::factory()->create(['region_id' => $region->id]);

    Livewire::test(ListRegions::class)->assertCanSeeTableRecords([$region]);
    Livewire::test(EditRegion::class, ['record' => $region->public_id])->assertActionHidden('delete');

    expect(fn () => app(DeleteReferenceRecord::class)->handle($region, Actor::forAdmin($this->admin)))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('conflict')->and($e->status)->toBe(409));
});

it('creates a category that does not allow auctions', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm(['code' => 'livestock', 'sort_order' => 3, 'name' => ['ar' => 'مواشي', 'en' => 'Livestock'], 'auction_allowed' => false, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::query()->where('code', 'livestock')->sole();
    expect($category->auction_allowed)->toBeFalse();

    Livewire::test(EditCategory::class, ['record' => $category->public_id])
        ->fillForm(['auction_allowed' => true])
        ->call('save');

    expect($category->refresh()->auction_allowed)->toBeTrue();
});

it('creates a close reason whose kind and code are fixed afterwards', function () {
    Livewire::test(CreateCloseReason::class)
        ->fillForm([
            'code' => 'cancel_force_majeure',
            'kind' => CloseReasonKind::Cancel->value,
            'name' => ['ar' => 'قوة قاهرة', 'en' => 'Force majeure'],
            'requires_note' => true,
            'sort_order' => 9,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $reason = CloseReason::query()->where('code', 'cancel_force_majeure')->sole();
    expect($reason->kind)->toBe(CloseReasonKind::Cancel)->and($reason->requires_note)->toBeTrue();

    Livewire::test(EditCloseReason::class, ['record' => $reason->public_id])
        ->assertFormFieldIsDisabled('kind')
        ->assertFormFieldIsDisabled('code');
});

it('creates and edits a preset with structured rules', function () {
    Livewire::test(CreateCompetitionPreset::class)
        ->fillForm([
            'code' => 'quick_tender',
            'sort_order' => 4,
            'name' => ['ar' => 'مناقصة سريعة', 'en' => 'Quick tender'],
            'description' => ['ar' => 'قالب قصير', 'en' => 'A short preset'],
            'direction' => 'tender',
            'format' => 'live',
            'is_active' => true,
            'rules' => [
                'min_step_bps' => 100,
                'amount_granularity_minor' => '100',
                'must_beat' => 'own',
                'rank_visibility' => 'leading_flag',
                'result_publication' => 'outcome_only',
                'show_prices' => false,
                'min_participants' => 3,
                'auto_extend' => ['enabled' => true, 'window_seconds' => 120, 'by_seconds' => 120, 'max_extensions' => 5],
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $preset = CompetitionPreset::query()->where('code', 'quick_tender')->sole();
    expect($preset->rules['min_step_bps'])->toBe(100)
        ->and($preset->rules['amount_granularity_minor'])->toBe(100)
        ->and($preset->rules['auto_extend'])->toMatchArray(['enabled' => true, 'window_seconds' => 120, 'max_extensions' => 5])
        ->and($preset->rules['min_participants'])->toBe(3);

    Livewire::test(EditCompetitionPreset::class, ['record' => $preset->public_id])
        ->fillForm(['rules.auto_extend.enabled' => true, 'rules.auto_extend.window_seconds' => null])
        ->call('save')
        ->assertHasFormErrors(['rules.auto_extend.window_seconds' => 'required']);
});
