<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Database\Factories\OrganizationFactory;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Files\File;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A company on BAFO: issuer, participant or both (ARCHITECTURE §5.3 `organizations`).
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $legal_name_ar
 * @property string|null $legal_name_en
 * @property string $cr_number
 * @property bool $vat_registered
 * @property string|null $vat_number
 * @property int $region_id
 * @property string $city
 * @property string|null $address_building_number
 * @property string|null $address_street
 * @property string|null $address_district
 * @property string|null $address_postal_code
 * @property string|null $address_additional_number
 * @property string|null $address_short
 * @property string|null $website
 * @property string $email
 * @property string $phone
 * @property int|null $logo_file_id
 * @property int|null $profile_file_id
 * @property bool $visible_in_suggestions
 * @property OrganizationStatus $status
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $suspended_at
 * @property string|null $suspension_reason
 * @property bool $api_enabled
 * @property bool $auction_enabled
 * @property bool $sponsorship_enabled
 * @property CarbonImmutable|null $trial_used_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    /**
     * The columns that make the billing profile complete (ARCHITECTURE §5.3); `vat_number` is
     * also required when `vat_registered`.
     */
    public const array BILLING_PROFILE_FIELDS = [
        'legal_name_ar',
        'cr_number',
        'city',
        'address_building_number',
        'address_street',
        'address_district',
        'address_postal_code',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'legal_name_ar',
        'legal_name_en',
        'cr_number',
        'vat_registered',
        'vat_number',
        'region_id',
        'city',
        'address_building_number',
        'address_street',
        'address_district',
        'address_postal_code',
        'address_additional_number',
        'address_short',
        'website',
        'email',
        'phone',
        'logo_file_id',
        'profile_file_id',
        'visible_in_suggestions',
        'status',
        'verified_at',
        'suspended_at',
        'suspension_reason',
        'api_enabled',
        'auction_enabled',
        'sponsorship_enabled',
        'trial_used_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Whether an invitation addressed to `$email` (and to `$crNumber`, when the issuer's address
     * book has one) may be bound to this organization without a claim (SECURITY_REVIEW S-12):
     * the address is an active member's verified e-mail, or the platform verified this
     * organization and the CR number is its own. Self-declared contact e-mails and CR numbers of
     * unverified organizations prove nothing.
     */
    public function isProvenRecipient(string $email, ?string $crNumber = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if (User::provenOrganizationIdFor($email) === $this->id) {
            return true;
        }

        return $this->isVerified() && $crNumber !== null && $crNumber !== '' && $crNumber === $this->cr_number;
    }

    /**
     * Missing billing-profile columns (ARCHITECTURE §5.3), empty when the profile is complete.
     *
     * @return list<string>
     */
    public function missingBillingProfileFields(): array
    {
        $required = self::BILLING_PROFILE_FIELDS;

        if ($this->vat_registered) {
            $required[] = 'vat_number';
        }

        return array_values(array_filter(
            $required,
            fn (string $column): bool => blank($this->getAttribute($column)),
        ));
    }

    public function isBillingProfileComplete(): bool
    {
        return $this->missingBillingProfileFields() === [];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'organization_category');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'logo_file_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function profileFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'profile_file_id');
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasOne<Membership, $this>
     */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->where('role', OrgRole::Owner->value);
    }

    /**
     * @return HasManyThrough<User, Membership, $this>
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Membership::class, 'organization_id', 'id', 'id', 'user_id');
    }

    /**
     * Competitions this organization issues.
     *
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    /**
     * Invitations addressed to this organization.
     *
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * The issuer's own vendor directory.
     *
     * @return HasMany<Vendor, $this>
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    /**
     * @return HasMany<ApiClient, $this>
     */
    public function apiClients(): HasMany
    {
        return $this->hasMany(ApiClient::class);
    }

    /**
     * @return HasMany<WebhookEndpoint, $this>
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Organization-scoped coupons and vouchers.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<AccountDeletionRequest, $this>
     */
    public function accountDeletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => mb_strtolower(trim($value)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'visible_in_suggestions' => 'boolean',
            'status' => OrganizationStatus::class,
            'verified_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'api_enabled' => 'boolean',
            'auction_enabled' => 'boolean',
            'sponsorship_enabled' => 'boolean',
            'trial_used_at' => 'immutable_datetime',
        ];
    }
}
