<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\OfferFactory;
use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Channel;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An accepted offer: a row of the append-only, hash-chained ledger (ARCHITECTURE §5.6 `offers`).
 *
 * The table rejects UPDATE and DELETE with a trigger, so an Offer is inserted once and never
 * saved again; voids live in `offer_voids`. `seq` is gapless per competition and `hash` chains
 * each row to the previous one (`hashFor()`). Only `created_at` (= `accepted_at`, tstz6).
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property int $participant_id
 * @property int $organization_id
 * @property int $submitted_by_user_id
 * @property int $seq
 * @property OfferStage $stage
 * @property int $amount_minor
 * @property int $rank_key
 * @property CarbonImmutable $accepted_at
 * @property string $idempotency_key
 * @property Channel $channel
 * @property string|null $ip
 * @property string|null $user_agent
 * @property bool $outlier_confirmed
 * @property string|null $prev_hash
 * @property string $hash
 * @property CarbonImmutable|null $created_at
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory, HasPublicId, UsesPreciseTimestamps;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'participant_id',
        'organization_id',
        'submitted_by_user_id',
        'seq',
        'stage',
        'amount_minor',
        'rank_key',
        'accepted_at',
        'idempotency_key',
        'channel',
        'ip',
        'user_agent',
        'outlier_confirmed',
        'prev_hash',
        'hash',
        'created_at',
    ];

    /**
     * The ledger hash of ARCHITECTURE §5.6:
     * sha256(prev_hash|competition public id|seq|participant public id|amount|stage|accepted_at µs Z).
     */
    public static function hashFor(
        ?string $prevHash,
        string $competitionPublicId,
        int $seq,
        string $participantPublicId,
        int $amountMinor,
        OfferStage $stage,
        CarbonInterface $acceptedAt,
    ): string {
        return hash('sha256', implode('|', [
            $prevHash ?? '',
            $competitionPublicId,
            (string) $seq,
            $participantPublicId,
            (string) $amountMinor,
            $stage->value,
            $acceptedAt->toImmutable()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ]));
    }

    /**
     * The `rank_key` of ARCHITECTURE §7.1: ascending is best first (tender: amount, auction: −amount).
     */
    public static function rankKeyFor(int $directionSign, int $amountMinor): int
    {
        return -$directionSign * $amountMinor;
    }

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
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * The participant organization (denormalised).
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
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasOne<OfferVoid, $this>
     */
    public function void(): HasOne
    {
        return $this->hasOne(OfferVoid::class);
    }

    protected static function newFactory(): OfferFactory
    {
        return OfferFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'stage' => OfferStage::class,
            'amount_minor' => MinorAmount::class,
            'rank_key' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'channel' => Channel::class,
            'outlier_confirmed' => 'boolean',
        ];
    }
}
