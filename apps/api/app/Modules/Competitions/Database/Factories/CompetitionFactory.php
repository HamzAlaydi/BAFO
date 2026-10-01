<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Factories;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\CompetitionSource;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Database\Factories\Support\SaudiData;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * A draft live tender with the `standard_live_tender` rules (ARCHITECTURE §5.2): must beat own
 * offer by 0.5%, leading flag, auto-extend 180/180/10, a 60-minute final window, and a ceiling
 * start price. Lifecycle states (`scheduled()`, `live()`, `closed()`, …) set the status, the
 * schedule and the values derived at publish (§7.2) consistently; they bypass the Actions.
 *
 * @extends Factory<Competition>
 */
final class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    /**
     * @var list<string>
     */
    private const array TENDER_TITLES = [
        'توريد أجهزة حاسب آلي محمولة',
        'صيانة وتشغيل المباني الإدارية',
        'توريد مستلزمات مكتبية للعام المالي 2027',
        'نقل وتخزين البضائع بين المستودعات',
        'توريد أجهزة ومستلزمات طبية',
        'تطوير بوابة إلكترونية لخدمات العملاء',
    ];

    /**
     * @var list<string>
     */
    private const array AUCTION_TITLES = [
        'بيع معدات صناعية فائضة',
        'بيع خردة حديد ومعادن',
        'بيع أثاث مكتبي مستعمل',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $opensAt = CarbonImmutable::now()->addDay()->startOfHour();

        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => null,
            'created_by_api_client_id' => null,
            'source' => CompetitionSource::Web,
            'title' => $this->faker->randomElement(self::TENDER_TITLES),
            'description' => 'نطاق العمل: توريد وتركيب وتشغيل وفق كراسة الشروط والمواصفات المرفقة، مع ضمان لمدة سنة من تاريخ الاستلام.',
            'category_id' => Category::factory(),
            'category_other_text' => null,
            'region_id' => Region::factory(),
            'direction' => Direction::Tender,
            'format' => Format::Live,
            'status' => CompetitionStatus::Draft,
            'currency' => 'SAR',
            'preset_code' => 'standard_live_tender',
            'start_price_minor' => SaudiData::halalas($this->faker, 100_000, 900_000),
            'reserve_price_minor' => null,
            'min_step_minor' => null,
            'min_step_bps' => 50,
            'amount_granularity_minor' => 100,
            'must_beat' => MustBeat::Own,
            'rank_visibility' => RankVisibility::LeadingFlag,
            'show_prices' => false,
            'auto_extend_enabled' => true,
            'auto_extend_window_seconds' => 180,
            'auto_extend_by_seconds' => 180,
            'auto_extend_max' => 10,
            'final_window_minutes' => 60,
            'bafo_round_enabled' => false,
            'bafo_duration_minutes' => null,
            'min_participants' => 2,
            'result_publication' => ResultPublication::OutcomeOnly,
            'bidding_opens_at' => $opensAt,
            'scheduled_close_at' => $opensAt->addDays(2),
        ];
    }

    public function createdBy(User $user): self
    {
        return $this->state(['created_by_user_id' => $user->id]);
    }

    /**
     * The `surplus_sale_auction` rules: must beat the leading offer by SAR 500, prices shown,
     * auto-extend 120/120/20, no final window. The issuer has `auction_enabled`.
     */
    public function auction(): self
    {
        return $this->state(fn (): array => [
            'organization_id' => Organization::factory()->auctionEnabled(),
            'title' => $this->faker->randomElement(self::AUCTION_TITLES),
            'direction' => Direction::Auction,
            'preset_code' => 'surplus_sale_auction',
            'start_price_minor' => SaudiData::halalas($this->faker, 20_000, 200_000),
            'min_step_minor' => 50_000,
            'min_step_bps' => null,
            'must_beat' => MustBeat::Best,
            'rank_visibility' => RankVisibility::LeadingFlag,
            'show_prices' => true,
            'auto_extend_enabled' => true,
            'auto_extend_window_seconds' => 120,
            'auto_extend_by_seconds' => 120,
            'auto_extend_max' => 20,
            'final_window_minutes' => null,
        ]);
    }

    /**
     * The `sealed_rfq` rules (§7.2 R1): no rank, no prices, no step, no auto-extend, no final
     * window; BAFO round on (60 minutes).
     */
    public function sealed(): self
    {
        return $this->state([
            'format' => Format::Sealed,
            'preset_code' => 'sealed_rfq',
            'min_step_minor' => null,
            'min_step_bps' => null,
            'must_beat' => null,
            'rank_visibility' => RankVisibility::None,
            'show_prices' => false,
            'auto_extend_enabled' => false,
            'auto_extend_window_seconds' => null,
            'auto_extend_by_seconds' => null,
            'auto_extend_max' => null,
            'final_window_minutes' => null,
            'bafo_round_enabled' => true,
            'bafo_duration_minutes' => 60,
        ]);
    }

    /**
     * No final pricing window: the live phase is `open` from the start.
     */
    public function withoutFinalWindow(): self
    {
        return $this->state(['final_window_minutes' => null]);
    }

    public function withBafoRound(int $durationMinutes = 60): self
    {
        return $this->state(['bafo_round_enabled' => true, 'bafo_duration_minutes' => $durationMinutes]);
    }

    /**
     * Published, bidding opens tomorrow.
     */
    public function scheduled(): self
    {
        $opensAt = CarbonImmutable::now()->addDay()->startOfHour();

        return $this->lifecycle(CompetitionStatus::Scheduled, $opensAt, $opensAt->addDays(2));
    }

    /**
     * Bidding opened an hour ago and closes in two hours (phase `initial` with the default
     * 60-minute final window).
     */
    public function live(): self
    {
        $now = CarbonImmutable::now();

        return $this->lifecycle(CompetitionStatus::Live, $now->subHour(), $now->addHours(2));
    }

    /**
     * Live, closing in 30 minutes: inside the default 60-minute final window.
     */
    public function inFinalWindow(): self
    {
        $now = CarbonImmutable::now();

        return $this->lifecycle(CompetitionStatus::Live, $now->subHours(3), $now->addMinutes(30))
            ->state(fn (array $attributes): array => [
                'final_window_started_at' => $attributes['final_window_starts_at'],
            ]);
    }

    /**
     * Bidding closed an hour ago; the issuer is evaluating.
     */
    public function closed(): self
    {
        $now = CarbonImmutable::now();

        return $this->lifecycle(CompetitionStatus::Closed, $now->subHours(4), $now->subHour());
    }

    public function inBafoRound(): self
    {
        $now = CarbonImmutable::now();

        return $this->withBafoRound()
            ->lifecycle(CompetitionStatus::BafoRound, $now->subHours(4), $now->subHour());
    }

    public function awarded(): self
    {
        $now = CarbonImmutable::now();

        return $this->lifecycle(CompetitionStatus::Awarded, $now->subDays(2), $now->subDay())
            ->state(fn (): array => ['awarded_at' => CarbonImmutable::now()->subHours(2)]);
    }

    public function notAwarded(): self
    {
        $now = CarbonImmutable::now();

        return $this->lifecycle(CompetitionStatus::NotAwarded, $now->subDays(2), $now->subDay())
            ->state(fn (): array => [
                'not_awarded_at' => CarbonImmutable::now()->subHours(2),
                'not_awarded_reason_id' => CloseReason::factory()->kind(CloseReasonKind::NotAwarded),
            ]);
    }

    /**
     * Cancelled while scheduled.
     */
    public function cancelled(): self
    {
        $opensAt = CarbonImmutable::now()->addDay()->startOfHour();

        return $this->lifecycle(CompetitionStatus::Cancelled, $opensAt, $opensAt->addDays(2))
            ->state(fn (): array => [
                'cancelled_at' => CarbonImmutable::now(),
                'cancel_reason_id' => CloseReason::factory()->kind(CloseReasonKind::Cancel),
                'cancel_note' => null,
            ]);
    }

    /**
     * Status, schedule and the values derived at publish (ARCHITECTURE §7.2): reference number,
     * effective close, hard stop, final window start and invitation cut-off, plus the lifecycle
     * stamps the status implies.
     */
    private function lifecycle(CompetitionStatus $status, CarbonImmutable $opensAt, CarbonImmutable $closeAt): self
    {
        return $this->state(function (array $attributes) use ($status, $opensAt, $closeAt): array {
            $direction = $attributes['direction'] instanceof Direction
                ? $attributes['direction']
                : Direction::from((string) $attributes['direction']);
            $format = $attributes['format'] instanceof Format
                ? $attributes['format']
                : Format::from((string) $attributes['format']);

            $publishedAt = $opensAt->min(CarbonImmutable::now())->subHour();
            $finalWindowMinutes = $attributes['final_window_minutes'];
            $finalWindowStartsAt = is_int($finalWindowMinutes) ? $closeAt->subMinutes($finalWindowMinutes) : null;
            $hardStopAt = $attributes['auto_extend_enabled'] === true
                ? $closeAt->addSeconds((int) $attributes['auto_extend_max'] * (int) $attributes['auto_extend_by_seconds'])
                : null;
            $cutoff = $finalWindowStartsAt ?? $closeAt->subMinutes(60);

            $opened = ! in_array($status, [CompetitionStatus::Scheduled, CompetitionStatus::Cancelled], true);
            $closed = $opened && $status !== CompetitionStatus::Live;

            return [
                'status' => $status,
                'reference_no' => self::referenceNumber($direction, $publishedAt),
                'published_at' => $publishedAt,
                'bidding_opens_at' => $opensAt,
                'scheduled_close_at' => $closeAt,
                'effective_close_at' => $closeAt,
                'hard_stop_at' => $hardStopAt,
                'final_window_starts_at' => $finalWindowStartsAt,
                'invitation_cutoff_at' => $cutoff->max($opensAt),
                'opened_at' => $opened ? $opensAt : null,
                'closed_at' => $closed ? $closeAt : null,
                'offers_opened_at' => $closed && $format === Format::Sealed ? $closeAt : null,
            ];
        });
    }

    /**
     * `BAFO-{T|A}-{YYYY Riyadh}-{nextval padded to 6}` from `competition_reference_seq` (§5.5).
     */
    private static function referenceNumber(Direction $direction, CarbonImmutable $publishedAt): string
    {
        $next = (int) DB::scalar("select nextval('competition_reference_seq')");

        return sprintf(
            'BAFO-%s-%s-%06d',
            $direction === Direction::Tender ? 'T' : 'A',
            $publishedAt->setTimezone('Asia/Riyadh')->format('Y'),
            $next,
        );
    }
}
