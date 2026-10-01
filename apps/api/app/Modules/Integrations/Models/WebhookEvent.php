<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Database\Factories\WebhookEventFactory;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A row of the webhook outbox (ARCHITECTURE §5.4 `webhook_events`, §14.5), written by the
 * synchronous listeners. The public id is the Standard Webhooks `webhook-id`. Only `created_at`.
 * `occurred_at` is tstz6, so dates are written with microseconds (the precision-0 columns round).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property string $type
 * @property string $subject_type
 * @property int $subject_id
 * @property int $sequence
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $dispatched_at
 * @property CarbonImmutable|null $created_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory, HasPublicId, UsesPreciseTimestamps;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'type',
        'subject_type',
        'subject_id',
        'sequence',
        'payload',
        'occurred_at',
        'dispatched_at',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    protected static function newFactory(): WebhookEventFactory
    {
        return WebhookEventFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'sequence' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
        ];
    }
}
