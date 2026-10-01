<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Database\Factories\ExternalRefFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An ERP key attached to a vendor, competition, invitation, award or organization (ARCHITECTURE
 * §5.4 `external_refs`). `refable_type` is a morph alias. Internal: no public id.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $refable_type
 * @property int $refable_id
 * @property string $system
 * @property string $type
 * @property string $value
 * @property string|null $number
 * @property string|null $url
 * @property int|null $created_by_api_client_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ExternalRef extends Model
{
    /** @use HasFactory<ExternalRefFactory> */
    use HasFactory;

    /** `sap_s4`, …, or `custom:<name>` (ARCHITECTURE §5.4). */
    public const string SYSTEM_PATTERN = '/^[a-z0-9_]+(:[a-z0-9_]+)?$/';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'refable_type',
        'refable_id',
        'system',
        'type',
        'value',
        'number',
        'url',
        'created_by_api_client_id',
    ];

    /**
     * The owner organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function refable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function createdByApiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'created_by_api_client_id');
    }

    protected static function newFactory(): ExternalRefFactory
    {
        return ExternalRefFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refable_id' => 'integer',
        ];
    }
}
