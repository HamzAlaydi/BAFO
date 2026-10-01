<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Database\Factories\CompetitionAttachmentFactory;
use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Files\File;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A competition document or external link (ARCHITECTURE §5.5 `competition_attachments`).
 * Visibility by kind is in §8.5.
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property AttachmentKind $kind
 * @property int|null $file_id
 * @property string|null $title
 * @property string|null $url
 * @property bool $is_addendum
 * @property int|null $uploaded_by_user_id
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CompetitionAttachment extends Model
{
    /** @use HasFactory<CompetitionAttachmentFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'kind',
        'file_id',
        'title',
        'url',
        'is_addendum',
        'uploaded_by_user_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    protected static function newFactory(): CompetitionAttachmentFactory
    {
        return CompetitionAttachmentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'is_addendum' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
