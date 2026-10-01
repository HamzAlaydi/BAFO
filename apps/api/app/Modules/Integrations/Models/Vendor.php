<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Database\Factories\VendorFactory;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An entry of the issuer's own counterparty directory, the R1 "partner" (ARCHITECTURE §5.4
 * `vendors`). Blocked vendors cannot be invited.
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property string $name
 * @property string|null $name_en
 * @property string|null $cr_number
 * @property string|null $vat_number
 * @property string $email
 * @property string|null $contact_name
 * @property string|null $phone
 * @property int|null $region_id
 * @property string|null $city
 * @property VendorStatus $status
 * @property int|null $linked_organization_id
 * @property VendorSource $source
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'name_en',
        'cr_number',
        'vat_number',
        'email',
        'contact_name',
        'phone',
        'region_id',
        'city',
        'status',
        'linked_organization_id',
        'source',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    public function isBlocked(): bool
    {
        return $this->status === VendorStatus::Blocked;
    }

    /**
     * The owning (issuer) organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The BAFO organization matched by e-mail or CR.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function linkedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'linked_organization_id');
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
        return $this->belongsToMany(Category::class, 'vendor_category');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return MorphMany<ExternalRef, $this>
     */
    public function externalRefs(): MorphMany
    {
        return $this->morphMany(ExternalRef::class, 'refable');
    }

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
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
            'status' => VendorStatus::class,
            'source' => VendorSource::class,
        ];
    }
}
