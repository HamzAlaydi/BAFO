<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CloseReasonFactory;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\Concerns\HasTranslatedAttributes;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reason for cancelling, closing without award, justifying an award or voiding an offer
 * (ARCHITECTURE §5.2 `close_reasons`). The "Other" reasons require a note.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property CloseReasonKind $kind
 * @property array{ar: string, en: string} $name
 * @property bool $requires_note
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CloseReason extends Model
{
    /** @use HasFactory<CloseReasonFactory> */
    use HasFactory, HasPublicId, HasTranslatedAttributes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'kind',
        'name',
        'requires_note',
        'sort_order',
        'is_active',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfKind(Builder $query, CloseReasonKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    protected static function newFactory(): CloseReasonFactory
    {
        return CloseReasonFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CloseReasonKind::class,
            'name' => 'array',
            'requires_note' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
