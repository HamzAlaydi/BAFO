<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Models;

use App\Modules\Competitions\Database\Factories\CommentFactory;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Q&A entry (ARCHITECTURE §5.5 `comments`): a question or a reply (one level). Participant
 * authors are shown by alias only.
 *
 * @property int $id
 * @property string $public_id
 * @property int $competition_id
 * @property int|null $parent_id
 * @property int $author_user_id
 * @property int $author_organization_id
 * @property int|null $author_participant_id
 * @property bool $is_issuer
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'parent_id',
        'author_user_id',
        'author_organization_id',
        'author_participant_id',
        'is_issuer',
        'body',
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function authorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'author_organization_id');
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function authorParticipant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'author_participant_id');
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_issuer' => 'boolean',
        ];
    }
}
