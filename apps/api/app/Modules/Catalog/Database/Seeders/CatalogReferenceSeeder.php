<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Enums\PresetTier;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Catalog reference data of ARCHITECTURE §5.2: the 13 Saudi administrative regions, the
 * categories, the close reasons, the three reference rules presets and the six tier presets of
 * RELEASE_SCOPE.md §2.2. Idempotent: `updateOrCreate` by `code`, so it runs on every `db:seed`.
 */
final class CatalogReferenceSeeder extends Seeder
{
    /**
     * The 13 administrative regions (مناطق المملكة الإدارية), in the official order.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    public const array REGIONS = [
        ['RIY', 'الرياض', 'Riyadh'],
        ['MAK', 'مكة المكرمة', 'Makkah'],
        ['MED', 'المدينة المنورة', 'Madinah'],
        ['EAS', 'المنطقة الشرقية', 'Eastern Province'],
        ['QAS', 'القصيم', 'Al-Qassim'],
        ['ASR', 'عسير', 'Asir'],
        ['TAB', 'تبوك', 'Tabuk'],
        ['HAI', 'حائل', 'Hail'],
        ['NBO', 'الحدود الشمالية', 'Northern Borders'],
        ['JAZ', 'جازان', 'Jazan'],
        ['NAJ', 'نجران', 'Najran'],
        ['BAH', 'الباحة', 'Al-Bahah'],
        ['JOU', 'الجوف', 'Al-Jouf'],
    ];

    /**
     * code, Arabic, English, is_other, auction_allowed. Vehicles and real estate cannot be
     * auctioned (R5 guardrail); "Other" asks for `category_other_text`.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: bool, 4: bool}>
     */
    public const array CATEGORIES = [
        ['it_hardware', 'أجهزة ومعدات تقنية', 'IT hardware', false, true],
        ['software_services', 'برمجيات وخدمات تقنية', 'Software and IT services', false, true],
        ['construction', 'مقاولات وإنشاءات', 'Construction and contracting', false, true],
        ['facility_management', 'تشغيل وصيانة المرافق', 'Facility management', false, true],
        ['office_supplies', 'مستلزمات مكتبية', 'Office supplies', false, true],
        ['logistics', 'نقل وخدمات لوجستية', 'Logistics and transport', false, true],
        ['medical_supplies', 'مستلزمات وأجهزة طبية', 'Medical supplies', false, true],
        ['marketing_printing', 'تسويق وطباعة', 'Marketing and printing', false, true],
        ['consulting', 'خدمات استشارية', 'Consulting', false, true],
        ['industrial_equipment', 'معدات صناعية', 'Industrial equipment', false, true],
        ['surplus_scrap', 'فائض ومخلفات', 'Surplus and scrap', false, true],
        ['vehicles', 'مركبات', 'Vehicles', false, false],
        ['real_estate', 'عقارات', 'Real estate', false, false],
        ['other', 'أخرى', 'Other', true, true],
    ];

    /**
     * code, kind, Arabic, English, requires_note. The "Other" reasons require a note.
     *
     * @var list<array{0: string, 1: CloseReasonKind, 2: string, 3: string, 4: bool}>
     */
    public const array CLOSE_REASONS = [
        ['cancel_requirements_changed', CloseReasonKind::Cancel, 'تغيّرت المتطلبات', 'Requirements changed', false],
        ['cancel_budget_withdrawn', CloseReasonKind::Cancel, 'سُحبت الميزانية المخصصة', 'Budget withdrawn', false],
        ['cancel_insufficient_participants', CloseReasonKind::Cancel, 'عدد المتنافسين غير كافٍ', 'Not enough participants', false],
        ['cancel_other', CloseReasonKind::Cancel, 'سبب آخر', 'Other reason', true],
        ['not_awarded_prices_above_budget', CloseReasonKind::NotAwarded, 'الأسعار أعلى من الميزانية', 'Prices above budget', false],
        ['not_awarded_no_compliant_offers', CloseReasonKind::NotAwarded, 'لا توجد عروض مطابقة للمتطلبات', 'No compliant offers', false],
        ['not_awarded_requirement_cancelled', CloseReasonKind::NotAwarded, 'أُلغي الاحتياج', 'Requirement cancelled', false],
        ['not_awarded_other', CloseReasonKind::NotAwarded, 'سبب آخر', 'Other reason', true],
        ['award_leader_non_compliant', CloseReasonKind::AwardJustification, 'العرض المتصدر غير مطابق للمتطلبات', 'The leading offer does not meet the requirements', false],
        ['award_commercial_terms', CloseReasonKind::AwardJustification, 'شروط تجارية أفضل', 'Better commercial terms', false],
        ['award_leader_unable_to_deliver', CloseReasonKind::AwardJustification, 'صاحب العرض المتصدر غير قادر على التنفيذ', 'The leading participant cannot deliver', false],
        ['award_other', CloseReasonKind::AwardJustification, 'سبب آخر', 'Other reason', true],
        ['void_participant_error', CloseReasonKind::VoidOffer, 'خطأ من المتنافس', 'Participant error', false],
        ['void_technical_issue', CloseReasonKind::VoidOffer, 'خلل تقني', 'Technical issue', false],
        ['void_other', CloseReasonKind::VoidOffer, 'سبب آخر', 'Other reason', true],
    ];

    /**
     * The preset tiers of RELEASE_SCOPE.md §2.2: tier => [name AR, name EN, description AR,
     * description EN, rules]. Each tier is seeded for both directions (`tender_live_<tier>`,
     * `auction_live_<tier>`), live format, with the same text. `rules` holds the RulesInput keys of
     * API.md §2.6 without prices.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: array<string, mixed>}>
     */
    public const array TIER_PRESETS = [
        'simple' => [
            'بسيطة',
            'Simple',
            'يحسّن كل متنافس عرضه بحرّية ويعرف فقط إن كان متصدراً. تُغلق المنافسة في موعدها دون تمديد.',
            'Each participant improves their own offer freely and only knows whether they are leading. The competition closes on time without extensions.',
            [
                'min_step_minor' => null,
                'min_step_bps' => null,
                'amount_granularity_minor' => 100,
                'must_beat' => 'own',
                'rank_visibility' => 'leading_flag',
                'show_prices' => false,
                'auto_extend' => ['enabled' => false, 'window_seconds' => null, 'by_seconds' => null, 'max_extensions' => null],
                'final_window_minutes' => null,
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                'min_participants' => 1,
                'result_publication' => 'outcome_only',
            ],
        ],
        'standard' => [
            'قياسية',
            'Standard',
            'حد أدنى للتحسين 0.5%. إذا تغيّر العرض المتصدر في آخر 3 دقائق يُمدَّد الإغلاق 3 دقائق (حتى 10 مرات). يعرف المتنافس إن كان متصدراً فقط.',
            'Minimum improvement 0.5%. If the leading offer changes in the last 3 minutes, closing extends by 3 minutes (up to 10 times). Participants only know whether they are leading.',
            [
                'min_step_minor' => null,
                'min_step_bps' => 50,
                'amount_granularity_minor' => 100,
                'must_beat' => 'own',
                'rank_visibility' => 'leading_flag',
                'show_prices' => false,
                'auto_extend' => ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10],
                'final_window_minutes' => null,
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                'min_participants' => 2,
                'result_publication' => 'outcome_only',
            ],
        ],
        'protected' => [
            'حماية قصوى',
            'Maximum protection',
            'لا يرى المتنافسون ترتيبهم ولا أسعار غيرهم. حد أدنى للتحسين 1%، وتمديد 5 دقائق عند أي تغيير في العرض المتصدر خلال آخر 5 دقائق (حتى 20 مرة).',
            'Participants see neither their rank nor other prices. Minimum improvement 1%, and closing extends by 5 minutes whenever the leading offer changes in the last 5 minutes (up to 20 times).',
            [
                'min_step_minor' => null,
                'min_step_bps' => 100,
                'amount_granularity_minor' => 100,
                'must_beat' => 'own',
                'rank_visibility' => 'none',
                'show_prices' => false,
                'auto_extend' => ['enabled' => true, 'window_seconds' => 300, 'by_seconds' => 300, 'max_extensions' => 20],
                'final_window_minutes' => null,
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                'min_participants' => 2,
                'result_publication' => 'outcome_only',
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedRegions();
            $this->seedCategories();
            $this->seedCloseReasons();
            $this->seedPresets();
        });
    }

    private function seedRegions(): void
    {
        foreach (self::REGIONS as $index => [$code, $ar, $en]) {
            Region::query()->updateOrCreate(
                ['code' => $code],
                ['name' => ['ar' => $ar, 'en' => $en], 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }

    private function seedCategories(): void
    {
        foreach (self::CATEGORIES as $index => [$code, $ar, $en, $isOther, $auctionAllowed]) {
            Category::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => ['ar' => $ar, 'en' => $en],
                    'is_other' => $isOther,
                    'auction_allowed' => $auctionAllowed,
                    // "Other" always sorts last.
                    'sort_order' => $isOther ? 999 : $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedCloseReasons(): void
    {
        foreach (self::CLOSE_REASONS as $index => [$code, $kind, $ar, $en, $requiresNote]) {
            CloseReason::query()->updateOrCreate(
                ['code' => $code],
                [
                    'kind' => $kind,
                    'name' => ['ar' => $ar, 'en' => $en],
                    'requires_note' => $requiresNote,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * The three reference presets of ARCHITECTURE §5.2 (untiered, `tier = null`), then the six tier
     * presets of RELEASE_SCOPE.md §2.2 (tender simple → protected, then auction). `rules` holds the
     * RulesInput keys of API.md §2.6 without prices.
     */
    private function seedPresets(): void
    {
        $presets = [
            [
                'code' => 'standard_live_tender',
                'name' => ['ar' => 'مناقصة مباشرة قياسية', 'en' => 'Standard live tender'],
                'description' => [
                    'ar' => 'يحسّن كل متنافس عرضه بخطوة لا تقل عن 0.5٪، مع مؤشر العرض المتصدر والتمديد التلقائي وفترة تسعير نهائية مدتها 60 دقيقة.',
                    'en' => 'Each participant improves their own offer by at least 0.5%, with the leading-offer flag, auto-extension and a 60-minute final pricing window.',
                ],
                'direction' => Direction::Tender,
                'format' => Format::Live,
                'tier' => null,
                'rules' => [
                    'min_step_minor' => null,
                    'min_step_bps' => 50,
                    'amount_granularity_minor' => 100,
                    'must_beat' => 'own',
                    'rank_visibility' => 'leading_flag',
                    'show_prices' => false,
                    'auto_extend' => ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10],
                    'final_window_minutes' => 60,
                    'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                    'min_participants' => 2,
                    'result_publication' => 'outcome_only',
                ],
            ],
            [
                'code' => 'sealed_rfq',
                'name' => ['ar' => 'طلب عروض أسعار مغلق', 'en' => 'Sealed request for quotation'],
                'description' => [
                    'ar' => 'يقدّم كل متنافس عرضاً مغلقاً لا يُفتح إلا عند الإغلاق، مع جولة عرض نهائي اختيارية مدتها 60 دقيقة.',
                    'en' => 'Each participant submits a sealed offer that opens only at the close, with an optional 60-minute best-and-final-offer round.',
                ],
                'direction' => Direction::Tender,
                'format' => Format::Sealed,
                'tier' => null,
                'rules' => [
                    'min_step_minor' => null,
                    'min_step_bps' => null,
                    'amount_granularity_minor' => 100,
                    'must_beat' => null,
                    'rank_visibility' => 'none',
                    'show_prices' => false,
                    'auto_extend' => ['enabled' => false, 'window_seconds' => null, 'by_seconds' => null, 'max_extensions' => null],
                    'final_window_minutes' => null,
                    'bafo_round' => ['enabled' => true, 'duration_minutes' => 60],
                    'min_participants' => 2,
                    'result_publication' => 'outcome_only',
                ],
            ],
            [
                'code' => 'surplus_sale_auction',
                'name' => ['ar' => 'مزايدة لبيع الفائض', 'en' => 'Surplus sale auction'],
                'description' => [
                    'ar' => 'يرفع كل متنافس العرض المتصدر بخطوة لا تقل عن 500 ر.س، مع إظهار الأسعار والتمديد التلقائي.',
                    'en' => 'Each participant raises the leading offer by at least SAR 500, with prices shown and auto-extension.',
                ],
                'direction' => Direction::Auction,
                'format' => Format::Live,
                'tier' => null,
                'rules' => [
                    'min_step_minor' => 50000,
                    'min_step_bps' => null,
                    'amount_granularity_minor' => 100,
                    'must_beat' => 'best',
                    'rank_visibility' => 'leading_flag',
                    'show_prices' => true,
                    'auto_extend' => ['enabled' => true, 'window_seconds' => 120, 'by_seconds' => 120, 'max_extensions' => 20],
                    'final_window_minutes' => null,
                    'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                    'min_participants' => 2,
                    'result_publication' => 'outcome_only',
                ],
            ],
        ];

        foreach ([Direction::Tender, Direction::Auction] as $direction) {
            foreach (self::TIER_PRESETS as $tier => [$nameAr, $nameEn, $descriptionAr, $descriptionEn, $rules]) {
                $presets[] = [
                    'code' => "{$direction->value}_live_{$tier}",
                    'name' => ['ar' => $nameAr, 'en' => $nameEn],
                    'description' => ['ar' => $descriptionAr, 'en' => $descriptionEn],
                    'direction' => $direction,
                    'format' => Format::Live,
                    'tier' => PresetTier::from($tier),
                    'rules' => $rules,
                ];
            }
        }

        foreach ($presets as $index => $preset) {
            CompetitionPreset::query()->updateOrCreate(
                ['code' => $preset['code']],
                [...$preset, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
