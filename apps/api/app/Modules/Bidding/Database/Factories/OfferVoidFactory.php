<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An admin void of a new offer with a `void_offer` reason.
 *
 * `voided_by_admin_id` is an unconstrained ref to `admins`; domain modules do not depend on the
 * Admin module (tests/Unit/ArchTest.php), so pass a real admin id when the test needs one.
 *
 * @extends Factory<OfferVoid>
 */
final class OfferVoidFactory extends Factory
{
    protected $model = OfferVoid::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'reason_id' => CloseReason::factory()->kind(CloseReasonKind::VoidOffer),
            'note' => 'أفاد المتنافس بإدخال المبلغ بالخطأ',
            'voided_by_admin_id' => 1,
        ];
    }
}
