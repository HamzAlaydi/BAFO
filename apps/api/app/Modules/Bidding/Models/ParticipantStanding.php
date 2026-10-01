<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\ParticipantStandingFactory;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A participant's derived standing (ARCHITECTURE §5.6 `participant_standings`): rebuildable from
 * the ledger minus voids; ranked by `Ranking::recompute` (§7.6). Primary key `participant_id`;
 * only `updated_at` (tstz6).
 *
 * @property int $participant_id
 * @property int $competition_id
 * @property int|null $current_offer_id
 * @property int|null $current_amount_minor
 * @property int|null $current_rank_key
 * @property CarbonImmutable|null $current_at
 * @property int|null $current_seq
 * @property int|null $first_amount_minor
 * @property int $offers_count
 * @property int|null $rank
 * @property bool $is_leader
 * @property bool $bafo_shortlisted
 * @property int|null $bafo_reference_amount_minor
 * @property int|null $bafo_offer_id
 * @property CarbonImmutable|null $last_offer_at
 * @property CarbonImmutable|null $updated_at
 */
class ParticipantStanding extends Model
{
    /** @use HasFactory<ParticipantStandingFactory> */
    use HasFactory, UsesPreciseTimestamps;

    public const CREATED_AT = null;

    protected $primaryKey = 'participant_id';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'participant_id',
        'competition_id',
        'current_offer_id',
        'current_amount_minor',
        'current_rank_key',
        'current_at',
        'current_seq',
        'first_amount_minor',
        'offers_count',
        'rank',
        'is_leader',
        'bafo_shortlisted',
        'bafo_reference_amount_minor',
        'bafo_offer_id',
        'last_offer_at',
    ];

    /**
     * The DB defaults of §5.6, so `firstOrCreate` returns a usable model without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'offers_count' => 0,
        'is_leader' => false,
        'bafo_shortlisted' => false,
    ];

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * The latest non-voided offer.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function currentOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'current_offer_id');
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function bafoOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'bafo_offer_id');
    }

    protected static function newFactory(): ParticipantStandingFactory
    {
        return ParticipantStandingFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_amount_minor' => MinorAmount::class,
            'current_rank_key' => 'integer',
            'current_at' => 'immutable_datetime',
            'current_seq' => 'integer',
            'first_amount_minor' => MinorAmount::class,
            'offers_count' => 'integer',
            'rank' => 'integer',
            'is_leader' => 'boolean',
            'bafo_shortlisted' => 'boolean',
            'bafo_reference_amount_minor' => MinorAmount::class,
            'last_offer_at' => 'immutable_datetime',
        ];
    }
}
