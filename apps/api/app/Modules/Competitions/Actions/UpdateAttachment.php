<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH …/attachments/{attachment}` (API.md §1.4): title and sort order.
 *
 * CONTRACT-GAP: the contract gives no status rule; the same statuses as adding apply (draft,
 * scheduled, live).
 */
final class UpdateAttachment
{
    /**
     * @param  array{title?: string|null, sort_order?: int}  $changes
     */
    public function handle(CompetitionAttachment $attachment, array $changes, Actor $actor): CompetitionAttachment
    {
        return DB::transaction(static function () use ($attachment, $changes, $actor): CompetitionAttachment {
            $locked = CompetitionAttachment::query()->with(['competition', 'file'])->whereKey($attachment->id)->lockForUpdate()->firstOrFail();

            AddAttachment::assertEditable($locked->competition);

            if (array_key_exists('title', $changes)) {
                $title = $changes['title'] !== null ? trim($changes['title']) : '';
                $file = $locked->file_id !== null ? $locked->file : null;
                $locked->title = $title !== '' ? $title : ($file !== null ? $file->original_name : $locked->title);
            }

            if (array_key_exists('sort_order', $changes)) {
                $locked->sort_order = $changes['sort_order'];
            }

            if ($locked->isDirty()) {
                $locked->save();

                AuditLogger::log('attachment.updated', $locked, AuditLogger::diff($locked, ['title', 'sort_order']),
                    actor: $actor, organizationId: $locked->competition->organization_id);
            }

            return $locked;
        });
    }
}
