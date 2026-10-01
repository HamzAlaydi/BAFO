<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\BafoRoundFactory;
use App\Modules\Bidding\Enums\BafoRoundStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The best-and-final-offer round (ARCHITECTURE §5.6 `bafo_rounds`, §7.11): at most one per
 * competition in the MVP. tstz6.
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property int $started_by_user_id
 * @property BafoRoundStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $cutoff_at
 * @property CarbonImmutable|null $ended_at
 * @property int $shortlist_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class BafoRound extends Model
{
    /** @use HasFactory<BafoRoundFactory> */
    use HasFactory, HasPublicId, UsesPreciseTimestamps;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'started_by_user_id',
        'status',
        'starts_at',
        'cutoff_at',
        'ended_at',
        'shortlist_count',
    ];

    public function isRunning(): bool
    {
        return $this->status === BafoRoundStatus::Running;
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    protected static function newFactory(): BafoRoundFactory
    {
        return BafoRoundFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BafoRoundStatus::class,
            'starts_at' => 'immutable_datetime',
            'cutoff_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'shortlist_count' => 'integer',
        ];
    }
}
