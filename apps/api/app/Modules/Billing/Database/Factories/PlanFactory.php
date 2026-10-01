<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 3-seat plan priced like `pro` (§5.7 demo seed: SAR 1,500 / month, SAR 15,000 / year, excl.
 * VAT) under a unique test code; the reference codes (`single`, `plus`, `pro`, `custom`) belong
 * to BillingReferenceSeeder.
 *
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'plan_'.$this->faker->unique()->numerify('#####'),
            'name' => ['ar' => 'باقة برو', 'en' => 'Pro'],
            'description' => ['ar' => 'للفرق التي تطرح منافسات بانتظام', 'en' => 'For teams that run competitions regularly'],
            'features' => [
                ['ar' => 'منافسات غير محدودة', 'en' => 'Unlimited competitions'],
                ['ar' => 'ثلاثة مستخدمين', 'en' => 'Three users'],
                ['ar' => 'تقارير النتائج', 'en' => 'Result reports'],
            ],
            'seats' => 3,
            'monthly_price_minor' => 150_000,
            'annual_price_minor' => 1_500_000,
            'monthly_list_price_minor' => 300_000,
            'annual_list_price_minor' => 3_000_000,
            'is_custom' => false,
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * The custom plan: no fixed seats or prices (priced per seat from settings).
     */
    public function custom(): self
    {
        return $this->state([
            'name' => ['ar' => 'باقة مخصصة', 'en' => 'Custom'],
            'seats' => null,
            'monthly_price_minor' => null,
            'annual_price_minor' => null,
            'monthly_list_price_minor' => null,
            'annual_list_price_minor' => null,
            'is_custom' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
