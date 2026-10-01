<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Support\ReferenceRecords;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an unused reference record from the panel (§16 CRUD). A record that other rows still
 * reference is refused with 409 `conflict`: deactivate it instead. Audited as `<noun>.deleted`
 * (the deleted record is named in the meta).
 *
 * CONTRACT-GAP: see SaveReferenceRecord.
 */
final class DeleteReferenceRecord
{
    public function handle(Model $record, Actor $actor): void
    {
        DB::transaction(static function () use ($record, $actor): void {
            if (ReferenceRecords::isInUse($record)) {
                throw new ApiException('conflict', 'admin.errors.record_in_use', 409);
            }

            $noun = ReferenceRecords::noun($record);
            $meta = array_filter([
                'record' => $noun,
                'id' => $record->getAttribute('public_id'),
                'code' => $record->getAttribute('code'),
            ], static fn (mixed $value): bool => $value !== null);

            $record->delete();

            AuditLogger::log($noun.'.deleted', null, [], $meta, $actor);
        });
    }
}
