<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ExportJobFactory;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ExportType;
use App\Modules\Integrations\Enums\JobStatus;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Files\File;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dashboard export (ARCHITECTURE §5.4 `export_jobs`, §14.8).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $created_by_user_id
 * @property ExportType $type
 * @property ExportFormat $format
 * @property array<string, mixed> $filters
 * @property JobStatus $status
 * @property int|null $file_id
 * @property int|null $row_count
 * @property string|null $failure_message
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ExportJob extends Model
{
    /** @use HasFactory<ExportJobFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'type',
        'format',
        'filters',
        'status',
        'file_id',
        'row_count',
        'failure_message',
        'finished_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

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
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    protected static function newFactory(): ExportJobFactory
    {
        return ExportJobFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExportType::class,
            'format' => ExportFormat::class,
            'filters' => 'array',
            'status' => JobStatus::class,
            'row_count' => 'integer',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
