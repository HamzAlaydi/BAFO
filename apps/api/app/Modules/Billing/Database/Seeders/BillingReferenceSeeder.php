<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Seeders;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The BAFO plans (ARCHITECTURE §5.7 seed, §17): single, plus, pro and custom. Prices are
 * integer halalas excl. VAT; the custom plan is priced per seat from the settings.
 *
 * CONTRACT-GAP: every plan value is admin-editable, so this seeder only creates missing codes
 * (`firstOrCreate` by code) and never overwrites the admin's prices on a re-run.
 */
final class BillingReferenceSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    public const array PLANS = [
        [
            'code' => 'single',
            'name' => ['ar' => 'باقة فردية', 'en' => 'Single'],
            'description' => ['ar' => 'لمستخدم واحد يطرح منافساته بنفسه', 'en' => 'For one user who runs their own competitions'],
            'features' => [
                ['ar' => 'مستخدم واحد', 'en' => 'One user'],
                ['ar' => 'منافسات غير محدودة', 'en' => 'Unlimited competitions'],
                ['ar' => 'مناقصات ومزايدات حية ومغلقة', 'en' => 'Live and sealed tenders and auctions'],
            ],
            'seats' => 1,
            'monthly_price_minor' => 31_500,
            'annual_price_minor' => 315_000,
            'monthly_list_price_minor' => 150_000,
            'annual_list_price_minor' => 1_500_000,
            'is_custom' => false,
            'is_featured' => false,
            'sort_order' => 1,
        ],
        [
            'code' => 'plus',
            'name' => ['ar' => 'باقة بلس', 'en' => 'Plus'],
            'description' => ['ar' => 'مدير حساب ومستخدم إضافي', 'en' => 'An account admin and one more user'],
            'features' => [
                ['ar' => 'مستخدمان', 'en' => 'Two users'],
                ['ar' => 'منافسات غير محدودة', 'en' => 'Unlimited competitions'],
                ['ar' => 'تقارير النتائج', 'en' => 'Result reports'],
            ],
            'seats' => 2,
            'monthly_price_minor' => 90_000,
            'annual_price_minor' => 900_000,
            'monthly_list_price_minor' => 200_000,
            'annual_list_price_minor' => 2_000_000,
            'is_custom' => false,
            'is_featured' => false,
            'sort_order' => 2,
        ],
        [
            'code' => 'pro',
            'name' => ['ar' => 'باقة برو', 'en' => 'Pro'],
            'description' => ['ar' => 'مدير حساب ومستخدمان إضافيان', 'en' => 'An account admin and two more users'],
            'features' => [
                ['ar' => 'ثلاثة مستخدمين', 'en' => 'Three users'],
                ['ar' => 'منافسات غير محدودة', 'en' => 'Unlimited competitions'],
                ['ar' => 'تقارير النتائج', 'en' => 'Result reports'],
                ['ar' => 'الربط البرمجي مع أنظمة تخطيط الموارد', 'en' => 'ERP integration through the API'],
            ],
            'seats' => 3,
            'monthly_price_minor' => 150_000,
            'annual_price_minor' => 1_500_000,
            'monthly_list_price_minor' => 300_000,
            'annual_list_price_minor' => 3_000_000,
            'is_custom' => false,
            'is_featured' => true,
            'sort_order' => 3,
        ],
        [
            'code' => 'custom',
            'name' => ['ar' => 'باقة مخصصة', 'en' => 'Custom'],
            'description' => ['ar' => 'للشركات التي تحتاج أكثر من ثلاثة مستخدمين', 'en' => 'For companies that need more than three users'],
            'features' => [
                ['ar' => 'عدد المستخدمين حسب احتياجك', 'en' => 'As many users as you need'],
                ['ar' => 'منافسات غير محدودة', 'en' => 'Unlimited competitions'],
                ['ar' => 'الربط البرمجي مع أنظمة تخطيط الموارد', 'en' => 'ERP integration through the API'],
            ],
            'seats' => null,
            'monthly_price_minor' => null,
            'annual_price_minor' => null,
            'monthly_list_price_minor' => null,
            'annual_list_price_minor' => null,
            'is_custom' => true,
            'is_featured' => false,
            'sort_order' => 4,
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            Plan::query()->firstOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}
