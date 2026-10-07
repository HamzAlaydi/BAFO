<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Seeders\CatalogReferenceSeeder;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Enums\PresetTier;
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

it('seeds the three reference presets without a tier', function () {
    (new CatalogReferenceSeeder)->run();

    $presets = CompetitionPreset::query()->whereNull('tier')->get()->keyBy('code');

    expect($presets->keys()->sort()->values()->all())->toBe(['sealed_rfq', 'standard_live_tender', 'surplus_sale_auction'])
        ->and($presets['sealed_rfq']->direction)->toBe(Direction::Tender)
        ->and($presets['sealed_rfq']->format)->toBe(Format::Sealed)
        ->and($presets['surplus_sale_auction']->direction)->toBe(Direction::Auction)
        ->and($presets['surplus_sale_auction']->rules['min_step_minor'])->toBe(50000)
        ->and($presets['standard_live_tender']->rules['final_window_minutes'])->toBe(60);
});

it('seeds the six tier presets of RELEASE_SCOPE.md §2.2 with their exact rules and text', function (string $tier, array $rules, array $name, array $description) {
    (new CatalogReferenceSeeder)->run();

    foreach (['tender', 'auction'] as $direction) {
        $preset = CompetitionPreset::query()->where('code', "{$direction}_live_{$tier}")->sole();

        expect($preset->tier)->toBe(PresetTier::from($tier))
            ->and($preset->direction)->toBe(Direction::from($direction))
            ->and($preset->format)->toBe(Format::Live)
            ->and($preset->is_active)->toBeTrue()
            ->and($preset->name)->toBe($name)
            ->and($preset->description)->toBe($description)
            ->and($preset->rules)->toEqual([
                'min_step_minor' => null,
                'amount_granularity_minor' => 100,
                'must_beat' => 'own',
                'show_prices' => false,
                'final_window_minutes' => null,
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                'result_publication' => 'outcome_only',
                ...$rules,
            ])
            ->and($preset->rules)->not->toHaveKeys(['start_price_minor', 'reserve_price_minor']);
    }
})->with([
    'simple' => ['simple', [
        'min_step_bps' => null,
        'rank_visibility' => 'leading_flag',
        'auto_extend' => ['enabled' => false, 'window_seconds' => null, 'by_seconds' => null, 'max_extensions' => null],
        'min_participants' => 1,
    ], ['ar' => 'بسيطة', 'en' => 'Simple'], [
        'ar' => 'يحسّن كل متنافس عرضه بحرّية ويعرف فقط إن كان متصدراً. تُغلق المنافسة في موعدها دون تمديد.',
        'en' => 'Each participant improves their own offer freely and only knows whether they are leading. The competition closes on time without extensions.',
    ]],
    'standard' => ['standard', [
        'min_step_bps' => 50,
        'rank_visibility' => 'leading_flag',
        'auto_extend' => ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10],
        'min_participants' => 2,
    ], ['ar' => 'قياسية', 'en' => 'Standard'], [
        'ar' => 'حد أدنى للتحسين 0.5%. إذا تغيّر العرض المتصدر في آخر 3 دقائق يُمدَّد الإغلاق 3 دقائق (حتى 10 مرات). يعرف المتنافس إن كان متصدراً فقط.',
        'en' => 'Minimum improvement 0.5%. If the leading offer changes in the last 3 minutes, closing extends by 3 minutes (up to 10 times). Participants only know whether they are leading.',
    ]],
    'protected' => ['protected', [
        'min_step_bps' => 100,
        'rank_visibility' => 'none',
        'auto_extend' => ['enabled' => true, 'window_seconds' => 300, 'by_seconds' => 300, 'max_extensions' => 20],
        'min_participants' => 2,
    ], ['ar' => 'حماية قصوى', 'en' => 'Maximum protection'], [
        'ar' => 'لا يرى المتنافسون ترتيبهم ولا أسعار غيرهم. حد أدنى للتحسين 1%، وتمديد 5 دقائق عند أي تغيير في العرض المتصدر خلال آخر 5 دقائق (حتى 20 مرة).',
        'en' => 'Participants see neither their rank nor other prices. Minimum improvement 1%, and closing extends by 5 minutes whenever the leading offer changes in the last 5 minutes (up to 20 times).',
    ]],
]);

it('orders the tier presets simple, standard, protected per direction after the reference presets', function () {
    (new CatalogReferenceSeeder)->run();

    expect(CompetitionPreset::query()->orderBy('sort_order')->pluck('code')->all())->toBe([
        'standard_live_tender', 'sealed_rfq', 'surplus_sale_auction',
        'tender_live_simple', 'tender_live_standard', 'tender_live_protected',
        'auction_live_simple', 'auction_live_standard', 'auction_live_protected',
    ]);
});

it('is idempotent and restores the seeded values', function () {
    $seeder = new CatalogReferenceSeeder;
    $seeder->run();

    Region::query()->where('code', 'RIY')->update(['name' => ['ar' => 'x', 'en' => 'x']]);
    CompetitionPreset::query()->where('code', 'tender_live_standard')->update(['tier' => null, 'rules' => json_encode(['min_participants' => 9])]);
    $seeder->run();

    expect(Region::query()->count())->toBe(13)
        ->and(Category::query()->count())->toBe(14)
        ->and(CloseReason::query()->count())->toBe(15)
        ->and(CompetitionPreset::query()->count())->toBe(9)
        ->and(CompetitionPreset::query()->whereNotNull('tier')->count())->toBe(6)
        ->and(Region::query()->where('code', 'RIY')->sole()->name)->toBe(['ar' => 'الرياض', 'en' => 'Riyadh'])
        ->and(CompetitionPreset::query()->where('code', 'tender_live_standard')->sole())
        ->tier->toBe(PresetTier::Standard)
        ->rules->toMatchArray(['min_participants' => 2, 'min_step_bps' => 50]);
});

it('runs from the database seeder', function () {
    expect(DatabaseSeeder::referenceSeeders())->toContain(CatalogReferenceSeeder::class);
});
