<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Models;

use App\Modules\Bidding\Database\Factories\CompetitionReportFactory;
use App\Modules\Bidding\Enums\ReportStatus;
use App\Modules\Competitions\Models\Competition;
use App\Support\Files\File;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result PDF of a competition in one locale (ARCHITECTURE §5.6 `competition_reports`, §7.14).
 * Internal: no public id.
 *
 * @property int $id
 * @property int $competition_id
 * @property string $locale
 * @property ReportStatus $status
 * @property int|null $file_id
 * @property int $live_version
 * @property CarbonImmutable|null $generated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CompetitionReport extends Model
{
    /** @use HasFactory<CompetitionReportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'competition_id',
        'locale',
        'status',
        'file_id',
        'live_version',
        'generated_at',
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

    protected static function newFactory(): CompetitionReportFactory
    {
        return CompetitionReportFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'live_version' => 'integer',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
