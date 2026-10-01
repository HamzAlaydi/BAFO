<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\OfferRejectionFactory;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rejected offer attempt, kept for disputes (ARCHITECTURE §5.6 `offer_rejections`, §7.4).
 * Written outside the offer transaction. Internal: no public id. Only `created_at` (tstz6).
 *
 * @property int $id
 * @property int $competition_id
 * @property int|null $participant_id
 * @property int|null $user_id
 * @property int|null $amount_minor
 * @property string $code
 * @property string|null $idempotency_key
 * @property string|null $stage
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $db_time
 * @property string|null $channel
 * @property string|null $ip
 * @property CarbonImmutable|null $created_at
 */
class OfferRejection extends Model
{
    /** @use HasFactory<OfferRejectionFactory> */
    use HasFactory, UsesPreciseTimestamps;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'participant_id',
        'user_id',
        'amount_minor',
        'code',
        'idempotency_key',
        'stage',
        'received_at',
        'db_time',
        'channel',
        'ip',
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
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): OfferRejectionFactory
    {
        return OfferRejectionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => MinorAmount::class,
            'received_at' => 'immutable_datetime',
            'db_time' => 'immutable_datetime',
        ];
    }
}
