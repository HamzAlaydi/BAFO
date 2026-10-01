<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\Region;
use Illuminate\Routing\Router;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Accounts::seedCatalog();
});

it('registers the Catalog endpoints of API.md §1.2 as guest routes', function (string $name, string $uri) {
    $route = app(Router::class)->getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe($uri)
        ->and($route?->methods())->toContain('GET')
        ->and($route?->excludedMiddleware())->toContain('auth:sanctum');
})->with([
    ['app.v1.lookups.index', 'api/app/v1/lookups'],
    ['app.v1.lookups.show', 'api/app/v1/lookups/{type}'],
]);

it('returns every lookup list without a token', function () {
    $response = $this->getJson('/api/app/v1/lookups')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'regions' => [['id', 'code', 'name']],
                'categories' => [['id', 'code', 'name', 'is_other', 'auction_allowed']],
                'close_reasons' => [['id', 'code', 'kind', 'name', 'requires_note']],
                'presets' => [['id', 'code', 'name', 'description', 'direction', 'format', 'rules']],
            ],
            'meta' => ['server_time'],
        ]);

    expect($response->json('data.regions'))->toHaveCount(13)
        ->and($response->json('data.regions.0'))->toBe([
            'id' => Region::query()->where('code', 'RIY')->value('public_id'),
            'code' => 'RIY',
            'name' => 'الرياض',
        ])
        ->and($response->json('data.categories'))->toHaveCount(14)
        ->and($response->json('data.close_reasons'))->toHaveCount(15)
        ->and($response->json('data.presets'))->toHaveCount(3);
});

it('names the lookups in the request language', function () {
    $this->getJson('/api/app/v1/lookups', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.regions.0.name', 'Riyadh')
        ->assertJsonPath('data.categories.0.name', 'IT hardware');
});

it('lists active rows only, ordered by sort_order', function () {
    Region::query()->where('code', 'MAK')->update(['is_active' => false]);
    Region::query()->where('code', 'JOU')->update(['sort_order' => 0]);

    $codes = collect($this->getJson('/api/app/v1/lookups/regions')->assertOk()->json('data'))->pluck('code');

    expect($codes)->toHaveCount(12)
        ->and($codes->first())->toBe('JOU')
        ->and($codes)->not->toContain('MAK');
});

it('flags the categories that need other text or forbid auctions', function () {
    $categories = collect($this->getJson('/api/app/v1/lookups/categories')->json('data'))->keyBy('code');

    expect($categories['other']['is_other'])->toBeTrue()
        ->and($categories['other'])->toBe($categories->last())
        ->and($categories['vehicles']['auction_allowed'])->toBeFalse()
        ->and($categories['real_estate']['auction_allowed'])->toBeFalse()
        ->and($categories['it_hardware']['auction_allowed'])->toBeTrue();
});

it('filters the close reasons by kind', function (CloseReasonKind $kind, int $count) {
    $reasons = collect($this->getJson('/api/app/v1/lookups/close-reasons?kind='.$kind->value)->assertOk()->json('data'));

    expect($reasons)->toHaveCount($count)
        ->and($reasons->pluck('kind')->unique()->all())->toBe([$kind->value])
        ->and($reasons->last()['requires_note'])->toBeTrue()
        ->and($reasons->first()['requires_note'])->toBeFalse();
})->with([
    [CloseReasonKind::Cancel, 4],
    [CloseReasonKind::NotAwarded, 4],
    [CloseReasonKind::AwardJustification, 4],
    [CloseReasonKind::VoidOffer, 3],
]);

it('rejects an unknown close reason kind', function () {
    $this->getJson('/api/app/v1/lookups/close-reasons?kind=refund')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['kind']);
});

it('answers 404 for an unknown lookup type', function () {
    $this->getJson('/api/app/v1/lookups/cities')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('returns the presets with rules and no prices', function () {
    $presets = collect($this->getJson('/api/app/v1/lookups/presets')->assertOk()->json('data'))->keyBy('code');

    expect($presets->keys()->all())->toBe(['standard_live_tender', 'sealed_rfq', 'surplus_sale_auction'])
        ->and($presets['standard_live_tender'])->toMatchArray(['direction' => 'tender', 'format' => 'live'])
        ->and($presets['standard_live_tender']['rules'])->toMatchArray([
            'min_step_bps' => 50,
            'must_beat' => 'own',
            'rank_visibility' => 'leading_flag',
            'show_prices' => false,
            'auto_extend' => ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10],
            'final_window_minutes' => 60,
            'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
        ])
        ->and($presets['sealed_rfq']['rules'])->toMatchArray([
            'must_beat' => null,
            'rank_visibility' => 'none',
            'bafo_round' => ['enabled' => true, 'duration_minutes' => 60],
        ])
        ->and($presets['surplus_sale_auction'])->toMatchArray(['direction' => 'auction', 'format' => 'live'])
        ->and($presets['surplus_sale_auction']['rules'])->toMatchArray([
            'must_beat' => 'best',
            'min_step_minor' => 50000,
            'show_prices' => true,
            'auto_extend' => ['enabled' => true, 'window_seconds' => 120, 'by_seconds' => 120, 'max_extensions' => 20],
        ]);

    foreach ($presets as $preset) {
        expect($preset['rules'])->not->toHaveKeys(['start_price_minor', 'reserve_price_minor']);
    }
});

it('sends an ETag and answers 304 when it matches', function () {
    $etag = $this->getJson('/api/app/v1/lookups')->assertOk()->headers->get('ETag');

    expect($etag)->toBeString()->not->toBeEmpty();

    $this->getJson('/api/app/v1/lookups', ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertNoContent(304);

    $this->getJson('/api/app/v1/lookups', ['If-None-Match' => $etag, 'Accept-Language' => 'en'])
        ->assertOk();
});

it('changes the ETag when the data changes', function () {
    $etag = $this->getJson('/api/app/v1/lookups')->headers->get('ETag');

    CloseReason::query()->where('code', 'cancel_other')->update(['is_active' => false]);

    $this->getJson('/api/app/v1/lookups', ['If-None-Match' => $etag])->assertOk();
    expect($this->getJson('/api/app/v1/lookups')->headers->get('ETag'))->not->toBe($etag);
});

it('also sends an ETag on a single list', function () {
    $etag = $this->getJson('/api/app/v1/lookups/categories')->assertOk()->headers->get('ETag');

    $this->getJson('/api/app/v1/lookups/categories', ['If-None-Match' => $etag])->assertStatus(304);
    expect(Category::query()->count())->toBe(14);
});
