<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\MembershipFactory;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\Permission;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's place in an organization (ARCHITECTURE §5.3 `memberships`; one per user, one owner
 * per organization). Permissions come from `OrgRole::permissions()` (§8.1).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $user_id
 * @property OrgRole $role
 * @property bool $can_award
 * @property bool $can_purchase
 * @property MembershipStatus $status
 * @property int|null $invited_by_user_id
 * @property string|null $invite_token_hash
 * @property CarbonImmutable|null $invite_expires_at
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'can_award',
        'can_purchase',
        'status',
        'invited_by_user_id',
        'invite_token_hash',
        'invite_expires_at',
        'joined_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'invite_token_hash',
    ];

    public function isOwner(): bool
    {
        return $this->role === OrgRole::Owner;
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return OrgRole::permissions($this);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrgRole::class,
            'can_award' => 'boolean',
            'can_purchase' => 'boolean',
            'status' => MembershipStatus::class,
            'invite_expires_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
        ];
    }
}
