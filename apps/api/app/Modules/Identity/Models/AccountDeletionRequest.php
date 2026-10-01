<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\AccountDeletionRequestFactory;
use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled account deletion (ARCHITECTURE §5.3 `account_deletion_requests`, §13.8). At most
 * one pending request per user.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $organization_id
 * @property DeletionScope $scope
 * @property string|null $reason
 * @property DeletionStatus $status
 * @property CarbonImmutable $scheduled_for
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AccountDeletionRequest extends Model
{
    /** @use HasFactory<AccountDeletionRequestFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'scope',
        'reason',
        'status',
        'scheduled_for',
        'cancelled_at',
        'completed_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): AccountDeletionRequestFactory
    {
        return AccountDeletionRequestFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => DeletionScope::class,
            'status' => DeletionStatus::class,
            'scheduled_for' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
