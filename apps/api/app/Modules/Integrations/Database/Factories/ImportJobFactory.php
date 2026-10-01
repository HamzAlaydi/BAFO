<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Factories;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\ImportMode;
use App\Modules\Integrations\Enums\ImportType;
use App\Modules\Integrations\Enums\JobStatus;
use App\Modules\Integrations\Models\ImportJob;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A queued vendor import (validate mode) with its uploaded CSV row.
 *
 * @extends Factory<ImportJob>
 */
final class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'type' => ImportType::Vendors,
            'mode' => ImportMode::Validate,
            'status' => JobStatus::Queued,
            'source_file_id' => static fn (array $attributes): int => File::factory()
                ->purpose(FilePurpose::ImportSource, 'csv', 'text/csv')
                ->create([
                    'organization_id' => $attributes['organization_id'],
                    'uploaded_by_user_id' => $attributes['created_by_user_id'],
                    'original_name' => 'vendors.csv',
                ])
                ->id,
            'errors_file_id' => null,
        ];
    }

    public function completed(int $rows = 25): self
    {
        return $this->state(fn (): array => [
            'status' => JobStatus::Completed,
            'total_rows' => $rows,
            'valid_rows' => $rows,
            'finished_at' => now(),
        ]);
    }
}
