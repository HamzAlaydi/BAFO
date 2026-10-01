<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloseReason>
 */
final class CloseReasonFactory extends Factory
{
    protected $model = CloseReason::class;

    /**
     * @var array<string, array{ar: string, en: string}>
     */
    private const array NAMES = [
        'cancel' => ['ar' => 'تغيّرت المتطلبات', 'en' => 'Requirements changed'],
        'not_awarded' => ['ar' => 'الأسعار أعلى من الميزانية', 'en' => 'Prices above budget'],
        'award_justification' => ['ar' => 'شروط تجارية أفضل', 'en' => 'Better commercial terms'],
        'void_offer' => ['ar' => 'خطأ من المتنافس', 'en' => 'Participant error'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'cancel_reason_'.$this->faker->unique()->numerify('#####'),
            'kind' => CloseReasonKind::Cancel,
            'name' => self::NAMES['cancel'],
            'requires_note' => false,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    public function kind(CloseReasonKind $kind): self
    {
        return $this->state(fn (): array => [
            'code' => $kind->value.'_reason_'.$this->faker->unique()->numerify('#####'),
            'kind' => $kind,
            'name' => self::NAMES[$kind->value],
        ]);
    }

    /**
     * An "Other" reason: a note is required.
     */
    public function requiresNote(): self
    {
        return $this->state([
            'name' => ['ar' => 'سبب آخر', 'en' => 'Other reason'],
            'requires_note' => true,
        ]);
    }
}
