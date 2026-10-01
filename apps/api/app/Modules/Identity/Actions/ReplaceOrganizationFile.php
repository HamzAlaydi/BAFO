<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\OrganizationUpdated;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The organization logo (public image ≤ 2 MB) and company profile (private PDF ≤ 20 MB):
 * `POST` stores a new file, `DELETE` (null upload) removes it. The previous file is deleted.
 */
final readonly class ReplaceOrganizationFile
{
    public function __construct(private FileStorage $files) {}

    public function handle(Organization $organization, FilePurpose $purpose, ?UploadedFile $upload, Actor $actor): Organization
    {
        $column = match ($purpose) {
            FilePurpose::OrganizationLogo => 'logo_file_id',
            FilePurpose::OrganizationProfile => 'profile_file_id',
            default => throw new InvalidArgumentException("Not an organization file purpose: [{$purpose->value}]."),
        };

        // Checked and written before the transaction: 422 file_type_not_allowed / file_too_large.
        $new = $upload !== null ? $this->files->store($upload, $purpose, $organization->id, $actor->userId) : null;

        return DB::transaction(function () use ($organization, $purpose, $column, $new, $actor): Organization {
            /** @var Organization $organization */
            $organization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $previousId = $organization->getAttribute($column);
            $previous = is_numeric($previousId) ? File::query()->find((int) $previousId) : null;

            if ($previous === null && $new === null) {
                return $organization;
            }

            $organization->forceFill([$column => $new?->id])->save();

            if ($previous !== null) {
                $this->files->delete($previous);
            }

            $action = $purpose === FilePurpose::OrganizationLogo ? 'organization.logo' : 'organization.profile_document';
            AuditLogger::log($action.($new !== null ? '_updated' : '_removed'), $organization, actor: $actor);

            event(new OrganizationUpdated($organization, [$column], $actor));

            return $organization;
        });
    }
}
