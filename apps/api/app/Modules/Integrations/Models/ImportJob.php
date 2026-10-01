<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ImportJobFactory;
use App\Modules\Integrations\Enums\ImportMode;
use App\Modules\Integrations\Enums\ImportType;
use App\Modules\Integrations\Enums\JobStatus;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Files\File;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CSV/XLSX vendor import (ARCHITECTURE §5.4 `import_jobs`, §14.7).
 *
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $created_by_user_id
 * @property ImportType $type
 * @property ImportMode $mode
 * @property JobStatus $status
 * @property int $source_file_id
 * @property int|null $errors_file_id
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $created_rows
 * @property int $updated_rows
 * @property int $error_rows
 * @property list<array{row: int, column: string|null, code: string, message: string}>|null $errors_preview
 * @property string|null $failure_message
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ImportJob extends Model
{
    /** @use HasFactory<ImportJobFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'type',
        'mode',
        'status',
        'source_file_id',
        'errors_file_id',
        'total_rows',
        'valid_rows',
        'created_rows',
        'updated_rows',
        'error_rows',
        'errors_preview',
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
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'source_file_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function errorsFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'errors_file_id');
    }

    protected static function newFactory(): ImportJobFactory
    {
        return ImportJobFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'mode' => ImportMode::class,
            'status' => JobStatus::class,
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'created_rows' => 'integer',
            'updated_rows' => 'integer',
            'error_rows' => 'integer',
            'errors_preview' => 'array',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
