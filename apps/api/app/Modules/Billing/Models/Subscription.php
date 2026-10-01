<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\SubscriptionFactory;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Identity\Models\Organization;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An organization's plan subscription: paid, trial or grant (ARCHITECTURE §5.7 `subscriptions`,
 * §6.5, §13.2). "Current" means `status = active AND starts_at <= now < ends_at`.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $plan_id
 * @property SubscriptionSource $source
 * @property BillingInterval|null $interval
 * @property int $seats
 * @property SubscriptionStatus $status
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property int|null $unit_price_minor
 * @property int|null $subtotal_minor
 * @property int|null $discount_minor
 * @property int|null $credit_minor
 * @property int|null $vat_minor
 * @property int|null $total_minor
 * @property int|null $payment_id
 * @property int|null $replaces_subscription_id
 * @property int|null $granted_by_admin_id
 * @property string|null $grant_reason
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $superseded_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $cancelled_at
 * @property list<int> $reminders_sent
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'plan_id',
        'source',
        'interval',
        'seats',
        'status',
        'starts_at',
        'ends_at',
        'unit_price_minor',
        'subtotal_minor',
        'discount_minor',
        'credit_minor',
        'vat_minor',
        'total_minor',
        'payment_id',
        'replaces_subscription_id',
        'granted_by_admin_id',
        'grant_reason',
        'activated_at',
        'superseded_at',
        'expired_at',
        'cancelled_at',
        'reminders_sent',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reminders_sent' => '[]',
    ];

    /**
     * `status = active AND starts_at <= $at < ends_at` (ARCHITECTURE §5.7).
     */
    public function isCurrentAt(CarbonInterface $at): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->starts_at !== null && $this->ends_at !== null
            && $this->starts_at->lessThanOrEqualTo($at) && $this->ends_at->greaterThan($at);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The subscription this one upgrades or renews.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_subscription_id');
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_subscription_id');
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => SubscriptionSource::class,
            'interval' => BillingInterval::class,
            'seats' => 'integer',
            'status' => SubscriptionStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'unit_price_minor' => MinorAmount::class,
            'subtotal_minor' => MinorAmount::class,
            'discount_minor' => MinorAmount::class,
            'credit_minor' => MinorAmount::class,
            'vat_minor' => MinorAmount::class,
            'total_minor' => MinorAmount::class,
            'activated_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reminders_sent' => 'array',
        ];
    }
}
