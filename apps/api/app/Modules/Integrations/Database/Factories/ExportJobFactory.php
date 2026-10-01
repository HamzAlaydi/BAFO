<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ExportType;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Models\ExportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A queued vendors export (XLSX).
 *
 * @extends Factory<ExportJob>
 */
final class ExportJobFactory extends Factory
{
    protected $model = ExportJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'type' => ExportType::Vendors,
            'format' => ExportFormat::Xlsx,
            'filters' => [],
            'status' => JobStatus::Queued,
            'file_id' => null,
        ];
    }
}
