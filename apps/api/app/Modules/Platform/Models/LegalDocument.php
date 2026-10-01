<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Database\Factories\LegalDocumentFactory;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * A version of a legal document in one locale (table legal_documents). A version is public
 * once `published_at` is set and reached; the latest published one is served by
 * GET /legal/{code} and announced by GET /app-config.
 *
 * @property int $id
 * @property string $public_id
 * @property LegalDocumentCode $code
 * @property string $locale
 * @property string $version
 * @property string $title
 * @property string $body_markdown
 * @property CarbonImmutable|null $published_at
 * @property int|null $created_by_admin_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    use HasPublicId;

    protected $table = 'legal_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'locale',
        'version',
        'title',
        'body_markdown',
        'published_at',
        'created_by_admin_id',
    ];

    public static function latestPublished(LegalDocumentCode $code, string $locale): ?self
    {
        return self::query()
            ->published()
            ->where('code', $code->value)
            ->where('locale', $locale)
            ->orderByDesc('published_at')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(Date::now());
    }

    /**
     * @param  Builder<LegalDocument>  $query
     * @return Builder<LegalDocument>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', Date::now());
    }

    protected static function newFactory(): LegalDocumentFactory
    {
        return LegalDocumentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => LegalDocumentCode::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
