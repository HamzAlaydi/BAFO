<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
final class RegionFactory extends Factory
{
    protected $model = Region::class;

    /**
     * @var list<array{ar: string, en: string}>
     */
    private const array NAMES = [
        ['ar' => 'الرياض', 'en' => 'Riyadh'],
        ['ar' => 'مكة المكرمة', 'en' => 'Makkah'],
        ['ar' => 'المدينة المنورة', 'en' => 'Madinah'],
        ['ar' => 'المنطقة الشرقية', 'en' => 'Eastern Province'],
        ['ar' => 'القصيم', 'en' => 'Al-Qassim'],
        ['ar' => 'عسير', 'en' => 'Asir'],
        ['ar' => 'تبوك', 'en' => 'Tabuk'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Never collides with the 3-letter seeded codes (RIY, MAK, …).
            'code' => 'R'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->randomElement(self::NAMES),
            'sort_order' => $this->faker->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
