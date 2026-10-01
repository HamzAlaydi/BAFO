<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\WebhookEndpointFactory;
use App\Modules\Integrations\Enums\WebhookDisabledReason;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer webhook URL (ARCHITECTURE §5.4 `webhook_endpoints`, §14.5). The signing secret is
 * stored with the `encrypted` cast; plain form `whsec_` + base64 of 32 random bytes.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property string $url
 * @property string|null $description
 * @property list<string> $event_types
 * @property string $secret
 * @property WebhookEndpointStatus $status
 * @property WebhookDisabledReason|null $disabled_reason
 * @property CarbonImmutable|null $failing_since
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 * @property int|null $created_by_user_id
 * @property int|null $created_by_api_client_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'url',
        'description',
        'event_types',
        'secret',
        'status',
        'disabled_reason',
        'failing_since',
        'last_success_at',
        'last_failure_at',
        'created_by_user_id',
        'created_by_api_client_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    public function isActive(): bool
    {
        return $this->status === WebhookEndpointStatus::Active;
    }

    /**
     * True when the endpoint subscribes to the type (or to every type with `["*"]`).
     */
    public function listensTo(string $eventType): bool
    {
        return in_array('*', $this->event_types, true) || in_array($eventType, $this->event_types, true);
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function createdByApiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'created_by_api_client_id');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    protected static function newFactory(): WebhookEndpointFactory
    {
        return WebhookEndpointFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_types' => 'array',
            'secret' => 'encrypted',
            'status' => WebhookEndpointStatus::class,
            'disabled_reason' => WebhookDisabledReason::class,
            'failing_since' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
        ];
    }
}
