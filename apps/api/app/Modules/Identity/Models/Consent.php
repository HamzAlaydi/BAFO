<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\ConsentFactory;
use App\Modules\Platform\Enums\LegalDocumentCode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acceptance of a legal document version (ARCHITECTURE §5.3 `consents`). No timestamps:
 * `accepted_at` is the record time.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $organization_id
 * @property LegalDocumentCode $document_code
 * @property string $document_version
 * @property string $locale
 * @property CarbonImmutable $accepted_at
 * @property string|null $ip
 * @property string|null $user_agent
 */
class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'document_code',
        'document_version',
        'locale',
        'accepted_at',
        'ip',
        'user_agent',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): ConsentFactory
    {
        return ConsentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_code' => LegalDocumentCode::class,
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
