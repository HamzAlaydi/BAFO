<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Factories;

use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * File rows for tests. The bytes are not written: put them on the (faked) disk at `path`
 * when the test downloads the file, or use FileStorage::storeContents() instead.
 *
 * @extends Factory<File>
 */
final class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicId = File::newPublicId();

        return [
            'public_id' => $publicId,
            'organization_id' => null,
            'uploaded_by_user_id' => null,
            'purpose' => FilePurpose::CompetitionAttachment,
            'disk' => 'private',
            'path' => FilePurpose::CompetitionAttachment->value.'/'.Date::now()->format('Y/m').'/'.$publicId.'.pdf',
            'original_name' => 'specs.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', $publicId),
        ];
    }

    public function purpose(FilePurpose $purpose, string $extension = 'pdf', string $mime = 'application/pdf'): self
    {
        return $this->state(fn (array $attributes): array => [
            'purpose' => $purpose,
            'disk' => $purpose->disk(),
            'path' => $purpose->value.'/'.Date::now()->format('Y/m').'/'.$attributes['public_id'].'.'.$extension,
            'extension' => $extension,
            'mime_type' => $mime,
            'original_name' => 'file.'.$extension,
        ]);
    }
}
