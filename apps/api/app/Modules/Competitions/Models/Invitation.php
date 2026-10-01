<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Models;

use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Database\Factories\InvitationFactory;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An invitation of one e-mail address to a competition (ARCHITECTURE §5.5 `invitations`, §6.2).
 * Only the sha256 of the token is stored.
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property string $email
 * @property string|null $name
 * @property int|null $organization_id
 * @property int|null $vendor_id
 * @property InvitationStatus $status
 * @property bool $sponsored_requested
 * @property string|null $token_hash
 * @property int|null $invited_by_user_id
 * @property int|null $invited_by_api_client_id
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $viewed_at
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $declined_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $expired_at
 * @property string|null $decline_reason
 * @property RevokeReason|null $revoke_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'email',
        'name',
        'organization_id',
        'vendor_id',
        'status',
        'sponsored_requested',
        'token_hash',
        'invited_by_user_id',
        'invited_by_api_client_id',
        'sent_at',
        'viewed_at',
        'joined_at',
        'declined_at',
        'revoked_at',
        'expired_at',
        'decline_reason',
        'revoke_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'sponsored_requested' => false,
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * The invitee organization, when known.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function invitedByApiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'invited_by_api_client_id');
    }

    /**
     * @return HasOne<Participant, $this>
     */
    public function participant(): HasOne
    {
        return $this->hasOne(Participant::class);
    }

    /**
     * @return HasMany<SponsoredPass, $this>
     */
    public function sponsoredPasses(): HasMany
    {
        return $this->hasMany(SponsoredPass::class);
    }

    /**
     * @return MorphMany<ExternalRef, $this>
     */
    public function externalRefs(): MorphMany
    {
        return $this->morphMany(ExternalRef::class, 'refable');
    }

    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => mb_strtolower(trim($value)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'sponsored_requested' => 'boolean',
            'sent_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'revoke_reason' => RevokeReason::class,
        ];
    }
}
