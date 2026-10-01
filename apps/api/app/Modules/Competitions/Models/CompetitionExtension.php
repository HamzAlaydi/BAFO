<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Models;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Database\Factories\CompetitionExtensionFactory;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A move of `effective_close_at` (ARCHITECTURE §5.5 `competition_extensions`): auto (anti-sniping,
 * §7.7), manual (§7.17) or admin. Written only by `CompetitionTimingService::extend()`. Only
 * `created_at` (tstz6).
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property ExtensionKind $kind
 * @property CarbonImmutable $previous_close_at
 * @property CarbonImmutable $new_close_at
 * @property int|null $triggered_by_offer_id
 * @property int|null $actor_user_id
 * @property int|null $actor_admin_id
 * @property string|null $reason
 * @property CarbonImmutable|null $created_at
 */
class CompetitionExtension extends Model
{
    /** @use HasFactory<CompetitionExtensionFactory> */
    use HasFactory, HasPublicId, UsesPreciseTimestamps;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'kind',
        'previous_close_at',
        'new_close_at',
        'triggered_by_offer_id',
        'actor_user_id',
        'actor_admin_id',
        'reason',
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * The accepted offer that triggered an automatic extension (no FK: offers are a later module).
     *
     * @return BelongsTo<Offer, $this>
     */
    public function triggeredByOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'triggered_by_offer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected static function newFactory(): CompetitionExtensionFactory
    {
        return CompetitionExtensionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExtensionKind::class,
            'previous_close_at' => 'immutable_datetime',
            'new_close_at' => 'immutable_datetime',
        ];
    }
}
