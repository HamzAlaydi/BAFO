<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\FileStorage;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE …/attachments/{attachment}` (API.md §1.4): draft or scheduled only, otherwise 409
 * `competition_not_editable`. The stored file goes with it (bytes deleted after commit).
 */
final readonly class DeleteAttachment
{
    public function __construct(private FileStorage $files) {}

    public function handle(CompetitionAttachment $attachment, Actor $actor): void
    {
        DB::transaction(function () use ($attachment, $actor): void {
            $locked = CompetitionAttachment::query()->with(['competition', 'file'])->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            $competition = $locked->competition;

            if (! in_array($competition->status, [CompetitionStatus::Draft, CompetitionStatus::Scheduled], true)) {
                throw UpdateCompetition::notEditable(['attachments']);
            }

            $file = $locked->file;

            AuditLogger::log('attachment.deleted', $locked, meta: ['competition_id' => $competition->public_id, 'title' => $locked->title],
                actor: $actor, organizationId: $competition->organization_id);

            $locked->delete();

            if ($file !== null) {
                $this->files->delete($file);
            }

            if ($competition->status !== CompetitionStatus::Draft) {
                event(new CompetitionUpdated($competition, ['attachments'], $actor));
            }
        });
    }
}
