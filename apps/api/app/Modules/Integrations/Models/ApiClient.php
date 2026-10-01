<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ApiClientFactory;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Enums\ApiScope;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Client as PassportClient;

/**
 * A public API client (ARCHITECTURE §5.4 `api_clients`, §14.1). Its public id is the OAuth
 * `client_id`; `oauth_client_id` points at Passport's `oauth_clients.id` (no FK).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property list<string> $scopes
 * @property ApiClientStatus $status
 * @property string|null $oauth_client_id
 * @property int|null $created_by_user_id
 * @property CarbonImmutable|null $last_used_at
 * @property string|null $last_used_ip
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ApiClient extends Model
{
    /** @use HasFactory<ApiClientFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'scopes',
        'status',
        'oauth_client_id',
        'created_by_user_id',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    public function isActive(): bool
    {
        return $this->status === ApiClientStatus::Active;
    }

    public function hasScope(ApiScope $scope): bool
    {
        return in_array($scope->value, $this->scopes, true);
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<PassportClient, $this>
     */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(PassportClient::class, 'oauth_client_id');
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function keys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<WebhookEndpoint, $this>
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class, 'created_by_api_client_id');
    }

    protected static function newFactory(): ApiClientFactory
    {
        return ApiClientFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'status' => ApiClientStatus::class,
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
