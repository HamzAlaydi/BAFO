<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\SponsoredPassFactory;
use App\Modules\Billing\Enums\PassReleaseReason;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sponsored participation pass (تصريح مشاركة مغطّاة) for one invitation (ARCHITECTURE §5.7
 * `sponsored_passes`, §6.3). At most one pending, reserved or joined pass per invitation.
 *
 * @property int $id
 * @property string $public_id
 * @property int $sponsorship_id
 * @property int $competition_id
 * @property int $invitation_id
 * @property int|null $organization_id
 * @property int|null $payment_id
 * @property PassSource $source
 * @property PassStatus $status
 * @property PassReleaseReason|null $release_reason
 * @property CarbonImmutable|null $hold_expires_at
 * @property CarbonImmutable|null $reserved_at
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $voided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SponsoredPass extends Model
{
    /** @use HasFactory<SponsoredPassFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sponsorship_id',
        'competition_id',
        'invitation_id',
        'organization_id',
        'payment_id',
        'source',
        'status',
        'release_reason',
        'hold_expires_at',
        'reserved_at',
        'joined_at',
        'released_at',
        'settled_at',
        'voided_at',
    ];

    /**
     * @return BelongsTo<CompetitionSponsorship, $this>
     */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(CompetitionSponsorship::class, 'sponsorship_id');
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Invitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * The invitee organization, once known.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The funding payment (null for `freed_slot` and `admin_grant`).
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected static function newFactory(): SponsoredPassFactory
    {
        return SponsoredPassFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PassSource::class,
            'status' => PassStatus::class,
            'release_reason' => PassReleaseReason::class,
            'hold_expires_at' => 'immutable_datetime',
            'reserved_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }
}
