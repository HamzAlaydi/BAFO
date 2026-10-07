<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\PresetTier;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Competitions\Enums\Format;
use App\Support\Features\ReleaseScope;
use Tests\Support\Identity\Accounts;

/*
|--------------------------------------------------------------------------
| Release scope: GET /lookups presets (RELEASE_SCOPE.md §1.5, §2.2)
|--------------------------------------------------------------------------
|
| Core offers the tier presets only; a preset that configures a hidden feature (sealed format,
| BAFO round, final window) or carries no tier (`advanced_rules`) is left out. Full offers every
| active preset. Every preset carries `tier`.
|
*/

beforeEach(function () {
    Accounts::seedCatalog();
});

/**
 * @return list<string>
 */
function catalogScopeTierCodes(): array
{
    return [
        'tender_live_simple', 'tender_live_standard', 'tender_live_protected',
        'auction_live_simple', 'auction_live_standard', 'auction_live_protected',
    ];
}

it('offers only the six tier presets in core, in order, each with its tier', function () {
    $this->releaseScope(ReleaseScope::Core);

    $presets = $this->getJson('/api/app/v1/lookups/presets')->assertOk()->json('data');

    expect(array_column($presets, 'code'))->toBe(catalogScopeTierCodes())
        ->and(array_column($presets, 'tier'))->toBe(['simple', 'standard', 'protected', 'simple', 'standard', 'protected'])
        ->and(array_unique(array_column($presets, 'format')))->toBe(['live']);

    $this->getJson('/api/app/v1/lookups', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonCount(6, 'data.presets')
        ->assertJsonPath('data.presets.1.code', 'tender_live_standard')
        ->assertJsonPath('data.presets.1.tier', 'standard')
        ->assertJsonPath('data.presets.1.name', 'Standard')
        ->assertJsonPath('data.presets.1.rules.min_step_bps', 50)
        ->assertJsonPath('data.presets.1.rules.auto_extend', ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10]);
});

it('offers every active preset in full, the untiered ones with a null tier', function () {
    $presets = collect($this->getJson('/api/app/v1/lookups/presets')->assertOk()->json('data'))->pluck('tier', 'code');

    expect($presets->all())->toBe([
        'standard_live_tender' => null,
        'sealed_rfq' => null,
        'surplus_sale_auction' => null,
        'tender_live_simple' => 'simple',
        'tender_live_standard' => 'standard',
        'tender_live_protected' => 'protected',
        'auction_live_simple' => 'simple',
        'auction_live_standard' => 'standard',
        'auction_live_protected' => 'protected',
    ]);
});

it('leaves out a tiered preset that configures a hidden feature in core', function (array $attributes, array $rules) {
    $preset = CompetitionPreset::factory()->tier(PresetTier::Standard)->create(['code' => 'admin_variant', ...$attributes]);
    $preset->forceFill(['rules' => [...$preset->rules, ...$rules]])->save();

    $this->releaseScope(ReleaseScope::Core);
    expect(array_column($this->getJson('/api/app/v1/lookups/presets')->json('data'), 'code'))->not->toContain('admin_variant');

    $this->releaseScope(ReleaseScope::Full);
    expect(array_column($this->getJson('/api/app/v1/lookups/presets')->json('data'), 'code'))->toContain('admin_variant');
})->with([
    'sealed format' => [['format' => Format::Sealed], ['must_beat' => null, 'rank_visibility' => 'none']],
    'BAFO round' => [[], ['bafo_round' => ['enabled' => true, 'duration_minutes' => 60]]],
    'final window' => [[], ['final_window_minutes' => 30]],
]);

it('keeps an inactive tier preset out in both scopes', function (ReleaseScope $scope) {
    CompetitionPreset::query()->where('code', 'tender_live_simple')->update(['is_active' => false]);
    $this->releaseScope($scope);

    expect(array_column($this->getJson('/api/app/v1/lookups/presets')->json('data'), 'code'))->not->toContain('tender_live_simple');
})->with(['core' => [ReleaseScope::Core], 'full' => [ReleaseScope::Full]]);

it('changes the lookups ETag when the scope changes', function () {
    $full = $this->getJson('/api/app/v1/lookups')->assertOk()->headers->get('ETag');

    $this->releaseScope(ReleaseScope::Core);
    $core = $this->getJson('/api/app/v1/lookups')->assertOk()->headers->get('ETag');

    expect($core)->not->toBe($full);

    // A client holding the full-scope ETag gets the new body, not a 304.
    $this->getJson('/api/app/v1/lookups', ['If-None-Match' => $full])->assertOk()->assertJsonCount(6, 'data.presets');
});
