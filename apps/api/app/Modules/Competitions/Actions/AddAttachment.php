<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\AttachmentAdded;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Adds a document (private file, purpose `competition_attachment`) or an external link to a
 * competition (API.md §1.4). Allowed in draft, scheduled and live; after publish the attachment
 * is an addendum. The file type and size rules are FileStorage's (422 `file_type_not_allowed`,
 * `file_too_large`).
 */
final readonly class AddAttachment
{
    public function __construct(private FileStorage $files) {}

    public function handle(
        Competition $competition,
        AttachmentKind $kind,
        ?UploadedFile $file,
        ?string $url,
        ?string $title,
        Actor $actor,
    ): CompetitionAttachment {
        self::assertEditable($competition);

        $stored = $file !== null && $kind !== AttachmentKind::ExternalLink
            ? $this->files->store($file, FilePurpose::CompetitionAttachment, $competition->organization_id, $actor->userId)
            : null;

        return DB::transaction(static function () use ($competition, $kind, $stored, $url, $title, $actor): CompetitionAttachment {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            self::assertEditable($locked);

            $title = $title !== null && trim($title) !== '' ? trim($title) : null;

            $attachment = new CompetitionAttachment;
            $attachment->forceFill([
                'competition_id' => $locked->id,
                'kind' => $kind,
                'file_id' => $stored?->id,
                'title' => $title ?? $stored?->original_name,
                'url' => $kind === AttachmentKind::ExternalLink ? $url : null,
                'is_addendum' => $locked->status !== CompetitionStatus::Draft,
                'uploaded_by_user_id' => $actor->userId,
                'sort_order' => (int) CompetitionAttachment::query()->where('competition_id', $locked->id)->max('sort_order') + 1,
            ])->save();

            $attachment->setRelation('file', $stored);

            AuditLogger::log('attachment.added', $attachment, meta: ['competition_id' => $locked->public_id, 'kind' => $kind->value],
                actor: $actor, organizationId: $locked->organization_id);

            event(new AttachmentAdded($attachment, $actor));

            return $attachment;
        });
    }

    public static function assertEditable(Competition $competition): void
    {
        if (! in_array($competition->status, [CompetitionStatus::Draft, CompetitionStatus::Scheduled, CompetitionStatus::Live], true)) {
            throw UpdateCompetition::notEditable(['attachments']);
        }
    }
}
