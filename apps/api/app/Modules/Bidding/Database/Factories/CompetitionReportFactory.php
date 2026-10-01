<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Database\Factories;

use App\Modules\Bidding\Enums\ReportStatus;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending Arabic result report of a closed competition (no PDF yet).
 *
 * @extends Factory<CompetitionReport>
 */
final class CompetitionReportFactory extends Factory
{
    protected $model = CompetitionReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->closed(),
            'locale' => 'ar',
            'status' => ReportStatus::Pending,
            'file_id' => null,
            'live_version' => 0,
            'generated_at' => null,
        ];
    }
}
