<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ApiKeyFactory;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An API key of a client (ARCHITECTURE §5.4 `api_keys`, §14.1). Only the prefix, the sha256 of
 * the full plain key and the last four characters are stored.
 *
 * @property int $id
 * @property string $public_id
 * @property int $api_client_id
 * @property string $prefix
 * @property string $key_hash
 * @property string $last_four
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $last_used_at
 * @property string|null $last_used_ip
 * @property int|null $created_by_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'api_client_id',
        'prefix',
        'key_hash',
        'last_four',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'last_used_ip',
        'created_by_user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    public function isUsableAt(CarbonInterface $at): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->greaterThan($at));
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected static function newFactory(): ApiKeyFactory
    {
        return ApiKeyFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }
}
