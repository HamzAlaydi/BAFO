<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\CompetitionLiveStateFactory;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The live bidding state of a published competition, 1:1 (ARCHITECTURE §5.6
 * `competition_live_states`). Created lazily with `firstOrCreate` under the competition row lock;
 * `version` is bumped on every audience-visible change (§9.3). Primary key `competition_id`;
 * only `updated_at` (tstz6).
 *
 * @property int $competition_id
 * @property int $version
 * @property int $last_seq
 * @property int|null $leader_participant_id
 * @property int|null $leader_offer_id
 * @property int|null $leader_amount_minor
 * @property int $accepted_offer_count
 * @property int $participants_with_offers
 * @property bool|null $reserve_met
 * @property string|null $ledger_head_hash
 * @property CarbonImmutable|null $updated_at
 */
class CompetitionLiveState extends Model
{
    /** @use HasFactory<CompetitionLiveStateFactory> */
    use HasFactory, UsesPreciseTimestamps;

    public const CREATED_AT = null;

    protected $primaryKey = 'competition_id';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'version',
        'last_seq',
        'leader_participant_id',
        'leader_offer_id',
        'leader_amount_minor',
        'accepted_offer_count',
        'participants_with_offers',
        'reserve_met',
        'ledger_head_hash',
    ];

    /**
     * The DB defaults of §5.6, so `firstOrCreate` returns a usable model without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 0,
        'last_seq' => 0,
        'accepted_offer_count' => 0,
        'participants_with_offers' => 0,
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function leaderParticipant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'leader_participant_id');
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function leaderOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'leader_offer_id');
    }

    protected static function newFactory(): CompetitionLiveStateFactory
    {
        return CompetitionLiveStateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'last_seq' => 'integer',
            'leader_offer_id' => 'integer',
            'leader_amount_minor' => MinorAmount::class,
            'accepted_offer_count' => 'integer',
            'participants_with_offers' => 'integer',
            'reserve_met' => 'boolean',
        ];
    }
}
