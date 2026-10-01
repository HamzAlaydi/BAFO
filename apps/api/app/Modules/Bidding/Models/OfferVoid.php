<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\OfferVoidFactory;
use App\Modules\Catalog\Models\CloseReason;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The platform admin's void of one offer (ARCHITECTURE §5.6 `offer_voids`, §7.13). Append-only
 * (trigger); at most one per offer. Only `created_at` (tstz6). `voided_by_admin_id` is an
 * unconstrained ref to `admins` (domain modules do not depend on the Admin module).
 *
 * @property int $id
 * @property string $public_id
 * @property int $offer_id
 * @property int $reason_id
 * @property string|null $note
 * @property int $voided_by_admin_id
 * @property CarbonImmutable|null $created_at
 */
class OfferVoid extends Model
{
    /** @use HasFactory<OfferVoidFactory> */
    use HasFactory, HasPublicId, UsesPreciseTimestamps;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'offer_id',
        'reason_id',
        'note',
        'voided_by_admin_id',
    ];

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<CloseReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(CloseReason::class, 'reason_id');
    }

    protected static function newFactory(): OfferVoidFactory
    {
        return OfferVoidFactory::new();
    }
}
