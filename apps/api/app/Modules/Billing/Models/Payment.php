<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\PaymentFactory;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A checkout order and its payment record (ARCHITECTURE §5.7 `payments`, §6.4, §13.1). Amounts
 * are integer halalas; `total = subtotal − discount − credit + vat`.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $created_by_user_id
 * @property PaymentPurpose $purpose
 * @property PaymentStatus $status
 * @property string $gateway
 * @property string|null $gateway_reference
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $credit_minor
 * @property int $vat_rate_bp
 * @property int $vat_minor
 * @property int $total_minor
 * @property int|null $coupon_id
 * @property string|null $idempotency_key
 * @property string $return_url
 * @property string|null $redirect_url
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $refunded_at
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property string|null $refund_reference
 * @property int|null $refunded_by_admin_id
 * @property string|null $manual_reference
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'purpose',
        'status',
        'gateway',
        'gateway_reference',
        'currency',
        'subtotal_minor',
        'discount_minor',
        'credit_minor',
        'vat_rate_bp',
        'vat_minor',
        'total_minor',
        'coupon_id',
        'idempotency_key',
        'return_url',
        'redirect_url',
        'metadata',
        'expires_at',
        'paid_at',
        'failed_at',
        'refunded_at',
        'failure_code',
        'failure_message',
        'refund_reference',
        'refunded_by_admin_id',
        'manual_reference',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'currency' => 'SAR',
        'discount_minor' => 0,
        'credit_minor' => 0,
        'vat_rate_bp' => 1500,
    ];

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
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
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<PaymentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentLine::class);
    }

    /**
     * @return HasOne<CouponRedemption, $this>
     */
    public function couponRedemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * The subscription bought with this payment (purpose `subscription`).
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * The passes funded by this payment (purpose `sponsorship`).
     *
     * @return HasMany<SponsoredPass, $this>
     */
    public function sponsoredPasses(): HasMany
    {
        return $this->hasMany(SponsoredPass::class);
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'subtotal_minor' => MinorAmount::class,
            'discount_minor' => MinorAmount::class,
            'credit_minor' => MinorAmount::class,
            'vat_rate_bp' => 'integer',
            'vat_minor' => MinorAmount::class,
            'total_minor' => MinorAmount::class,
            'metadata' => 'array',
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
