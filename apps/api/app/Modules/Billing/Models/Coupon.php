<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\CouponFactory;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A coupon or an organization-scoped voucher (ARCHITECTURE §5.7 `coupons`, §13.4). Codes are
 * stored in UPPERCASE; vouchers are `V-` + 10 `[A-Z0-9]`.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property CouponKind $kind
 * @property DiscountType $discount_type
 * @property int|null $percent_bps
 * @property int|null $amount_minor
 * @property int|null $balance_minor
 * @property CouponScope $applies_to
 * @property int|null $organization_id
 * @property int|null $max_redemptions
 * @property int $redemptions_count
 * @property int|null $per_organization_limit
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property bool $is_active
 * @property string|null $reason
 * @property int|null $source_competition_id
 * @property int|null $created_by_admin_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'kind',
        'discount_type',
        'percent_bps',
        'amount_minor',
        'balance_minor',
        'applies_to',
        'organization_id',
        'max_redemptions',
        'redemptions_count',
        'per_organization_limit',
        'valid_from',
        'valid_until',
        'is_active',
        'reason',
        'source_competition_id',
        'created_by_admin_id',
    ];

    public function isVoucher(): bool
    {
        return $this->kind === CouponKind::Voucher;
    }

    /**
     * The owning organization of an org-scoped coupon (every voucher).
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The competition whose unused passes produced this voucher.
     *
     * @return BelongsTo<Competition, $this>
     */
    public function sourceCompetition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'source_competition_id');
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => mb_strtoupper(trim($value)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CouponKind::class,
            'discount_type' => DiscountType::class,
            'percent_bps' => 'integer',
            'amount_minor' => MinorAmount::class,
            'balance_minor' => MinorAmount::class,
            'applies_to' => CouponScope::class,
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'per_organization_limit' => 'integer',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }
}
