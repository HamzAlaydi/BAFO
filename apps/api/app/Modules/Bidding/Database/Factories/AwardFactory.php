<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\ErpSyncStatus;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An issued award to the leading offer, consistent with the offer (competition, participant,
 * organization, amount) and awarded by an issuer member. The competition row is not moved to
 * `awarded` (use `Competition::factory()->awarded()` for the offer's competition if needed).
 *
 * @extends Factory<Award>
 */
final class AwardFactory extends Factory
{
    protected $model = Award::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'competition_id' => static fn (array $attributes): int => self::offer($attributes)->competition_id,
            'participant_id' => static fn (array $attributes): int => self::offer($attributes)->participant_id,
            'organization_id' => static fn (array $attributes): int => self::offer($attributes)->organization_id,
            'amount_minor' => static fn (array $attributes): int => self::offer($attributes)->amount_minor,
            'currency' => 'SAR',
            'status' => AwardStatus::Issued,
            'is_leading_offer' => true,
            'rank_at_award' => 1,
            'reserve_met' => null,
            'justification_reason_id' => null,
            'message_to_winner' => 'نبارك لكم الترسية، وسيتواصل معكم فريق المشتريات لاستكمال الإجراءات.',
            'awarded_by_user_id' => static fn (array $attributes): int => User::factory()
                ->withMembership(Organization::query()->findOrFail(
                    Competition::query()->findOrFail($attributes['competition_id'])->organization_id,
                ))
                ->create()
                ->id,
            'awarded_at' => CarbonImmutable::now(),
            'erp_sync_status' => ErpSyncStatus::NotRequired,
            'ledger_head_hash' => static fn (array $attributes): string => self::offer($attributes)->hash,
        ];
    }

    /**
     * Awarded to a non-leading offer: a justification is required (§7.12).
     */
    public function notLeading(int $rank = 2): self
    {
        return $this->state([
            'is_leading_offer' => false,
            'rank_at_award' => $rank,
            'justification_reason_id' => CloseReason::factory()->kind(CloseReasonKind::AwardJustification),
            'justification_text' => 'العرض المتصدر غير مطابق للمواصفات الفنية',
        ]);
    }

    public function revoked(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AwardStatus::Revoked,
            'revoked_by_user_id' => $attributes['awarded_by_user_id'],
            'revoked_at' => CarbonImmutable::now(),
            'revoke_reason' => 'اعتذر المتنافس عن التنفيذ',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function offer(array $attributes): Offer
    {
        return Offer::query()->findOrFail($attributes['offer_id']);
    }
}
