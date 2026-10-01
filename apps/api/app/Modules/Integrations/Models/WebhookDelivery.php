<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Integrations\Database\Factories\WebhookDeliveryFactory;
use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery of an outbox event to one endpoint (ARCHITECTURE §5.4 `webhook_deliveries`,
 * §14.5, §6.6).
 *
 * @property int $id
 * @property string $public_id
 * @property int $webhook_event_id
 * @property int $webhook_endpoint_id
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property CarbonImmutable|null $next_attempt_at
 * @property CarbonImmutable|null $last_attempt_at
 * @property int|null $last_http_status
 * @property string|null $last_error
 * @property string|null $last_response_excerpt
 * @property int|null $last_duration_ms
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'webhook_event_id',
        'webhook_endpoint_id',
        'status',
        'attempts',
        'next_attempt_at',
        'last_attempt_at',
        'last_http_status',
        'last_error',
        'last_response_excerpt',
        'last_duration_ms',
        'succeeded_at',
        'failed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /**
     * @return BelongsTo<WebhookEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    protected static function newFactory(): WebhookDeliveryFactory
    {
        return WebhookDeliveryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'last_http_status' => 'integer',
            'last_duration_ms' => 'integer',
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
