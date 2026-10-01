<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\CompetitionSponsorshipFactory;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Money\Casts\MinorAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The issuer's "fees covered" configuration of one competition (ARCHITECTURE §5.7
 * `competition_sponsorships`, §13.5). A row exists only when the mode is not none. Free slots =
 * `funded_passes − passes in (reserved, joined)`.
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property int $organization_id
 * @property SponsorshipMode $mode
 * @property int|null $max_passes
 * @property int $unit_price_minor
 * @property int $vat_rate_bp
 * @property int $funded_passes
 * @property SponsorshipStatus $status
 * @property CarbonImmutable|null $settled_at
 * @property int|null $unused_count
 * @property int|null $voucher_coupon_id
 * @property int $configured_by_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CompetitionSponsorship extends Model
{
    /** @use HasFactory<CompetitionSponsorshipFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'organization_id',
        'mode',
        'max_passes',
        'unit_price_minor',
        'vat_rate_bp',
        'funded_passes',
        'status',
        'settled_at',
        'unused_count',
        'voucher_coupon_id',
        'configured_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'vat_rate_bp' => 1500,
        'funded_passes' => 0,
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * The sponsor (the issuer).
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function voucherCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'voucher_coupon_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id');
    }

    /**
     * @return HasMany<SponsoredPass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(SponsoredPass::class, 'sponsorship_id');
    }

    protected static function newFactory(): CompetitionSponsorshipFactory
    {
        return CompetitionSponsorshipFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => SponsorshipMode::class,
            'max_passes' => 'integer',
            'unit_price_minor' => MinorAmount::class,
            'vat_rate_bp' => 'integer',
            'funded_passes' => 'integer',
            'status' => SponsorshipStatus::class,
            'settled_at' => 'immutable_datetime',
            'unused_count' => 'integer',
        ];
    }
}
