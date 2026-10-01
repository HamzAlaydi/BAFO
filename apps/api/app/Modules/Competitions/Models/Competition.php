<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Models;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\CompetitionSource;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A competition: a tender or an auction run by an issuer (ARCHITECTURE §5.5 `competitions`).
 *
 * Rules are typed columns locked at publish (D6). Only `CompetitionStateMachine::transition()`
 * writes `status` (§6.1). Every time column has microseconds (§4.3). Soft deletes apply to
 * drafts only.
 *
 * @property int $id
 * @property string $public_id
 * @property string|null $reference_no
 * @property int $organization_id
 * @property int|null $created_by_user_id
 * @property int|null $created_by_api_client_id
 * @property CompetitionSource $source
 * @property string $title
 * @property string|null $description
 * @property int $category_id
 * @property string|null $category_other_text
 * @property int $region_id
 * @property Direction $direction
 * @property Format $format
 * @property CompetitionStatus $status
 * @property string $currency
 * @property string|null $preset_code
 * @property int|null $start_price_minor
 * @property int|null $reserve_price_minor
 * @property int|null $min_step_minor
 * @property int|null $min_step_bps
 * @property int $amount_granularity_minor
 * @property MustBeat|null $must_beat
 * @property RankVisibility $rank_visibility
 * @property bool $show_prices
 * @property bool $auto_extend_enabled
 * @property int|null $auto_extend_window_seconds
 * @property int|null $auto_extend_by_seconds
 * @property int|null $auto_extend_max
 * @property int|null $final_window_minutes
 * @property bool $bafo_round_enabled
 * @property int|null $bafo_duration_minutes
 * @property int $min_participants
 * @property ResultPublication $result_publication
 * @property CarbonImmutable|null $bidding_opens_at
 * @property CarbonImmutable|null $scheduled_close_at
 * @property CarbonImmutable|null $effective_close_at
 * @property CarbonImmutable|null $hard_stop_at
 * @property CarbonImmutable|null $final_window_starts_at
 * @property CarbonImmutable|null $invitation_cutoff_at
 * @property int $extension_count
 * @property list<int> $notified_thresholds
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $final_window_started_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $offers_opened_at
 * @property CarbonImmutable|null $awarded_at
 * @property CarbonImmutable|null $not_awarded_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancel_reason_id
 * @property string|null $cancel_note
 * @property int|null $cancelled_by_user_id
 * @property int|null $cancelled_by_admin_id
 * @property int|null $not_awarded_reason_id
 * @property string|null $not_awarded_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory, HasPublicId, SoftDeletes, UsesPreciseTimestamps;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference_no',
        'organization_id',
        'created_by_user_id',
        'created_by_api_client_id',
        'source',
        'title',
        'description',
        'category_id',
        'category_other_text',
        'region_id',
        'direction',
        'format',
        'status',
        'currency',
        'preset_code',
        'start_price_minor',
        'reserve_price_minor',
        'min_step_minor',
        'min_step_bps',
        'amount_granularity_minor',
        'must_beat',
        'rank_visibility',
        'show_prices',
        'auto_extend_enabled',
        'auto_extend_window_seconds',
        'auto_extend_by_seconds',
        'auto_extend_max',
        'final_window_minutes',
        'bafo_round_enabled',
        'bafo_duration_minutes',
        'min_participants',
        'result_publication',
        'bidding_opens_at',
        'scheduled_close_at',
        'effective_close_at',
        'hard_stop_at',
        'final_window_starts_at',
        'invitation_cutoff_at',
        'extension_count',
        'notified_thresholds',
        'published_at',
        'opened_at',
        'final_window_started_at',
        'closed_at',
        'offers_opened_at',
        'awarded_at',
        'not_awarded_at',
        'cancelled_at',
        'cancel_reason_id',
        'cancel_note',
        'cancelled_by_user_id',
        'cancelled_by_admin_id',
        'not_awarded_reason_id',
        'not_awarded_note',
    ];

    /**
     * The DB defaults of §5.5, so a freshly created model carries them without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'SAR',
        'amount_granularity_minor' => 100,
        'rank_visibility' => 'leading_flag',
        'show_prices' => false,
        'auto_extend_enabled' => false,
        'bafo_round_enabled' => false,
        'min_participants' => 2,
        'result_publication' => 'outcome_only',
        'extension_count' => 0,
        'notified_thresholds' => '[]',
    ];

    /**
     * The derived phase while `live` (ARCHITECTURE §6.1), null in every other status.
     */
    public function phaseAt(CarbonInterface $at): ?Phase
    {
        if ($this->status !== CompetitionStatus::Live) {
            return null;
        }

        if ($this->format === Format::Sealed) {
            return Phase::Sealed;
        }

        if ($this->final_window_minutes === null) {
            return Phase::Open;
        }

        if ($this->final_window_starts_at === null || $at->lessThan($this->final_window_starts_at)) {
            return Phase::Initial;
        }

        return Phase::FinalWindow;
    }

    public function isDraft(): bool
    {
        return $this->status === CompetitionStatus::Draft;
    }

    public function isLive(): bool
    {
        return $this->status === CompetitionStatus::Live;
    }

    public function isSealed(): bool
    {
        return $this->format === Format::Sealed;
    }

    /**
     * The issuer.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function createdByApiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'created_by_api_client_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<CloseReason, $this>
     */
    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(CloseReason::class, 'cancel_reason_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return BelongsTo<CloseReason, $this>
     */
    public function notAwardedReason(): BelongsTo
    {
        return $this->belongsTo(CloseReason::class, 'not_awarded_reason_id');
    }

    /**
     * @return HasMany<CompetitionExtension, $this>
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(CompetitionExtension::class);
    }

    /**
     * @return HasMany<CompetitionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CompetitionAttachment::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return HasOne<CompetitionLiveState, $this>
     */
    public function liveState(): HasOne
    {
        return $this->hasOne(CompetitionLiveState::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return HasMany<OfferRejection, $this>
     */
    public function offerRejections(): HasMany
    {
        return $this->hasMany(OfferRejection::class);
    }

    /**
     * @return HasMany<ParticipantStanding, $this>
     */
    public function standings(): HasMany
    {
        return $this->hasMany(ParticipantStanding::class);
    }

    /**
     * @return HasOne<BafoRound, $this>
     */
    public function bafoRound(): HasOne
    {
        return $this->hasOne(BafoRound::class);
    }

    /**
     * Every award row, including revoked ones.
     *
     * @return HasMany<Award, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    /**
     * The one issued award, if any (partial unique index).
     *
     * @return HasOne<Award, $this>
     */
    public function issuedAward(): HasOne
    {
        return $this->hasOne(Award::class)->where('status', AwardStatus::Issued->value);
    }

    /**
     * @return HasMany<CompetitionReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(CompetitionReport::class);
    }

    /**
     * @return HasOne<CompetitionSponsorship, $this>
     */
    public function sponsorship(): HasOne
    {
        return $this->hasOne(CompetitionSponsorship::class);
    }

    /**
     * @return HasMany<SponsoredPass, $this>
     */
    public function sponsoredPasses(): HasMany
    {
        return $this->hasMany(SponsoredPass::class);
    }

    /**
     * @return MorphMany<ExternalRef, $this>
     */
    public function externalRefs(): MorphMany
    {
        return $this->morphMany(ExternalRef::class, 'refable');
    }

    protected static function newFactory(): CompetitionFactory
    {
        return CompetitionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CompetitionSource::class,
            'direction' => Direction::class,
            'format' => Format::class,
            'status' => CompetitionStatus::class,
            'start_price_minor' => MinorAmount::class,
            'reserve_price_minor' => MinorAmount::class,
            'min_step_minor' => MinorAmount::class,
            'min_step_bps' => 'integer',
            'amount_granularity_minor' => 'integer',
            'must_beat' => MustBeat::class,
            'rank_visibility' => RankVisibility::class,
            'show_prices' => 'boolean',
            'auto_extend_enabled' => 'boolean',
            'auto_extend_window_seconds' => 'integer',
            'auto_extend_by_seconds' => 'integer',
            'auto_extend_max' => 'integer',
            'final_window_minutes' => 'integer',
            'bafo_round_enabled' => 'boolean',
            'bafo_duration_minutes' => 'integer',
            'min_participants' => 'integer',
            'result_publication' => ResultPublication::class,
            'bidding_opens_at' => 'immutable_datetime',
            'scheduled_close_at' => 'immutable_datetime',
            'effective_close_at' => 'immutable_datetime',
            'hard_stop_at' => 'immutable_datetime',
            'final_window_starts_at' => 'immutable_datetime',
            'invitation_cutoff_at' => 'immutable_datetime',
            'extension_count' => 'integer',
            'notified_thresholds' => 'array',
            'published_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'final_window_started_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'offers_opened_at' => 'immutable_datetime',
            'awarded_at' => 'immutable_datetime',
            'not_awarded_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
