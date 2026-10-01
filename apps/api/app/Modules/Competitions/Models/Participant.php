<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Models;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Competitions\Database\Factories\ParticipantFactory;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An organization that joined a competition (ARCHITECTURE §5.5 `participants`): the
 * participation lock. Other participants see it only as "Participant {alias_no}".
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property int $organization_id
 * @property int $invitation_id
 * @property int $alias_no
 * @property EntitlementSource $entitlement_source
 * @property string $terms_version
 * @property CarbonImmutable $terms_accepted_at
 * @property string|null $terms_ip
 * @property int $joined_by_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'organization_id',
        'invitation_id',
        'alias_no',
        'entitlement_source',
        'terms_version',
        'terms_accepted_at',
        'terms_ip',
        'joined_by_user_id',
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Invitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function joinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joined_by_user_id');
    }

    /**
     * @return HasOne<ParticipantStanding, $this>
     */
    public function standing(): HasOne
    {
        return $this->hasOne(ParticipantStanding::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return HasMany<Award, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    /**
     * Q&A entries written as this participant.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author_participant_id');
    }

    protected static function newFactory(): ParticipantFactory
    {
        return ParticipantFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alias_no' => 'integer',
            'entitlement_source' => EntitlementSource::class,
            'terms_accepted_at' => 'immutable_datetime',
        ];
    }
}
