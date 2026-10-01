<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @var list<array{ar: string, en: string}>
     */
    private const array NAMES = [
        ['ar' => 'أجهزة تقنية', 'en' => 'IT hardware'],
        ['ar' => 'خدمات برمجية', 'en' => 'Software services'],
        ['ar' => 'مقاولات وإنشاءات', 'en' => 'Construction'],
        ['ar' => 'إدارة المرافق', 'en' => 'Facility management'],
        ['ar' => 'مستلزمات مكتبية', 'en' => 'Office supplies'],
        ['ar' => 'خدمات لوجستية', 'en' => 'Logistics'],
        ['ar' => 'مستلزمات طبية', 'en' => 'Medical supplies'],
        ['ar' => 'معدات صناعية', 'en' => 'Industrial equipment'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'category_'.$this->faker->unique()->numerify('#####'),
            'name' => $this->faker->randomElement(self::NAMES),
            'is_other' => false,
            'auction_allowed' => true,
            'sort_order' => $this->faker->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    /**
     * The "Other" category: competitions need `category_other_text`.
     */
    public function other(): self
    {
        return $this->state([
            'name' => ['ar' => 'أخرى', 'en' => 'Other'],
            'is_other' => true,
        ]);
    }

    /**
     * Real estate / vehicles: auctions are not allowed (R5 guardrail).
     */
    public function auctionNotAllowed(): self
    {
        return $this->state([
            'name' => ['ar' => 'مركبات', 'en' => 'Vehicles'],
            'auction_allowed' => false,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
