<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Seeders\CatalogReferenceSeeder;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use Database\Seeders\DatabaseSeeder;

it('seeds the 13 Saudi regions in Arabic and English', function () {
    (new CatalogReferenceSeeder)->run();

    $regions = Region::query()->orderBy('sort_order')->get();

    expect($regions->pluck('code')->all())->toBe(['RIY', 'MAK', 'MED', 'EAS', 'QAS', 'ASR', 'TAB', 'HAI', 'NBO', 'JAZ', 'NAJ', 'BAH', 'JOU'])
        ->and($regions->first()->name)->toBe(['ar' => 'الرياض', 'en' => 'Riyadh'])
        ->and($regions->firstWhere('code', 'EAS')?->name)->toBe(['ar' => 'المنطقة الشرقية', 'en' => 'Eastern Province'])
        ->and($regions->every(fn (Region $region): bool => $region->is_active))->toBeTrue();
});

it('seeds the categories with the auction guardrail and the Other category', function () {
    (new CatalogReferenceSeeder)->run();

    expect(Category::query()->pluck('code')->sort()->values()->all())->toBe(collect([
        'it_hardware', 'software_services', 'construction', 'facility_management', 'office_supplies', 'logistics',
        'medical_supplies', 'marketing_printing', 'consulting', 'industrial_equipment', 'surplus_scrap', 'vehicles',
        'real_estate', 'other',
    ])->sort()->values()->all())
        ->and(Category::query()->where('auction_allowed', false)->pluck('code')->sort()->values()->all())->toBe(['real_estate', 'vehicles'])
        ->and(Category::query()->where('is_other', true)->pluck('code')->all())->toBe(['other']);
});

it('seeds the close reasons of each kind, with a note required for Other', function () {
    (new CatalogReferenceSeeder)->run();

    expect(CloseReason::query()->ofKind(CloseReasonKind::Cancel)->count())->toBe(4)
        ->and(CloseReason::query()->ofKind(CloseReasonKind::NotAwarded)->count())->toBe(4)
        ->and(CloseReason::query()->ofKind(CloseReasonKind::AwardJustification)->count())->toBe(4)
        ->and(CloseReason::query()->ofKind(CloseReasonKind::VoidOffer)->count())->toBe(3)
        ->and(CloseReason::query()->where('requires_note', true)->pluck('code')->sort()->values()->all())
        ->toBe(['award_other', 'cancel_other', 'not_awarded_other', 'void_other']);
});

it('seeds the three rules presets', function () {
    (new CatalogReferenceSeeder)->run();

    $presets = CompetitionPreset::query()->get()->keyBy('code');

    expect($presets->keys()->sort()->values()->all())->toBe(['sealed_rfq', 'standard_live_tender', 'surplus_sale_auction'])
        ->and($presets['sealed_rfq']->direction)->toBe(Direction::Tender)
        ->and($presets['sealed_rfq']->format)->toBe(Format::Sealed)
        ->and($presets['surplus_sale_auction']->direction)->toBe(Direction::Auction)
        ->and($presets['surplus_sale_auction']->rules['min_step_minor'])->toBe(50000)
        ->and($presets['standard_live_tender']->rules['final_window_minutes'])->toBe(60);
});

it('is idempotent and restores the seeded values', function () {
    $seeder = new CatalogReferenceSeeder;
    $seeder->run();

    Region::query()->where('code', 'RIY')->update(['name' => ['ar' => 'x', 'en' => 'x']]);
    $seeder->run();

    expect(Region::query()->count())->toBe(13)
        ->and(Category::query()->count())->toBe(14)
        ->and(CloseReason::query()->count())->toBe(15)
        ->and(CompetitionPreset::query()->count())->toBe(3)
        ->and(Region::query()->where('code', 'RIY')->sole()->name)->toBe(['ar' => 'الرياض', 'en' => 'Riyadh']);
});

it('runs from the database seeder', function () {
    expect(DatabaseSeeder::referenceSeeders())->toContain(CatalogReferenceSeeder::class);
});
