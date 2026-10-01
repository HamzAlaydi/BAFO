<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Modules\Platform\Database\Factories\FileFactory;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored file (ARCHITECTURE §4.6, table `files`). Create and delete it only through
 * FileStorage. Private files are served by GET /api/app/v1/files/{file}/download after the
 * FileAccessRegistry allows it; public files (logos, avatars) have FileStorage::publicUrl().
 *
 * CONTRACT-GAP: the contract names the table and FileStorage but not the model's namespace;
 * it lives with the kernel service that owns it (App\Support\Files), morph alias `file`.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $organization_id
 * @property int|null $uploaded_by_user_id
 * @property FilePurpose $purpose
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $sha256
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    use HasPublicId;

    protected $table = 'files';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'organization_id',
        'uploaded_by_user_id',
        'purpose',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256',
    ];

    /**
     * The authenticated download path of API.md §2.5 (relative to the API host).
     */
    public function downloadPath(): string
    {
        return '/api/app/v1/files/'.$this->public_id.'/download';
    }

    public function isPublic(): bool
    {
        return $this->disk === 'public';
    }

    protected static function newFactory(): FileFactory
    {
        return FileFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => FilePurpose::class,
            'size_bytes' => 'integer',
        ];
    }
}
