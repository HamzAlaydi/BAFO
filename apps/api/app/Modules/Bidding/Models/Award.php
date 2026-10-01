<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\AwardFactory;
use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\ErpSyncStatus;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An award (ترسية) of a competition to one participant (ARCHITECTURE §5.6 `awards`, §7.12). At
 * most one `issued` award per competition; a new award after a revoke is a new row. tstz6.
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property int $participant_id
 * @property int $organization_id
 * @property int $offer_id
 * @property int $amount_minor
 * @property string $currency
 * @property AwardStatus $status
 * @property bool $is_leading_offer
 * @property int $rank_at_award
 * @property bool|null $reserve_met
 * @property int|null $justification_reason_id
 * @property string|null $justification_text
 * @property string|null $message_to_winner
 * @property string|null $internal_notes
 * @property int $awarded_by_user_id
 * @property CarbonImmutable $awarded_at
 * @property int|null $revoked_by_user_id
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property ErpSyncStatus $erp_sync_status
 * @property string|null $erp_sync_message
 * @property CarbonImmutable|null $erp_synced_at
 * @property string $ledger_head_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory, HasPublicId, UsesPreciseTimestamps;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'participant_id',
        'organization_id',
        'offer_id',
        'amount_minor',
        'currency',
        'status',
        'is_leading_offer',
        'rank_at_award',
        'reserve_met',
        'justification_reason_id',
        'justification_text',
        'message_to_winner',
        'internal_notes',
        'awarded_by_user_id',
        'awarded_at',
        'revoked_by_user_id',
        'revoked_at',
        'revoke_reason',
        'erp_sync_status',
        'erp_sync_message',
        'erp_synced_at',
        'ledger_head_hash',
    ];

    public function isIssued(): bool
    {
        return $this->status === AwardStatus::Issued;
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
     * The winner.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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
    public function justificationReason(): BelongsTo
    {
        return $this->belongsTo(CloseReason::class, 'justification_reason_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return MorphMany<ExternalRef, $this>
     */
    public function externalRefs(): MorphMany
    {
        return $this->morphMany(ExternalRef::class, 'refable');
    }

    protected static function newFactory(): AwardFactory
    {
        return AwardFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => MinorAmount::class,
            'status' => AwardStatus::class,
            'is_leading_offer' => 'boolean',
            'rank_at_award' => 'integer',
            'reserve_met' => 'boolean',
            'awarded_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'erp_sync_status' => ErpSyncStatus::class,
            'erp_synced_at' => 'immutable_datetime',
        ];
    }
}
