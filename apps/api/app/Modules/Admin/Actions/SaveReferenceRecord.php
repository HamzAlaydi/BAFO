<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\Support\ReferenceRecords;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a reference record from the panel (§16 CRUD): plans and coupons (Billing),
 * regions, categories, close reasons and presets (Catalog). Audited as `<noun>.created` or
 * `<noun>.updated`.
 *
 * CONTRACT-GAP: §16 lists these as CRUD without an owning-module Action (the Catalog handoff
 * confirms that the admin edits the lookup tables directly). The panel writes them through their
 * Eloquent models here, in one transaction with the audit entry. If Billing or Catalog add
 * Actions for them, the panel switches to those.
 */
final class SaveReferenceRecord
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $record  a new or existing record
     * @param  array<string, mixed>  $attributes  fillable attributes
     * @return TModel
     */
    public function handle(Model $record, array $attributes, Actor $actor): Model
    {
        return DB::transaction(static function () use ($record, $attributes, $actor): Model {
            $creating = ! $record->exists;

            $record->fill($attributes)->save();

            [$subject, $meta] = ReferenceRecords::auditSubject($record);

            AuditLogger::log(
                ReferenceRecords::noun($record).($creating ? '.created' : '.updated'),
                $subject,
                $creating ? [] : AuditLogger::diff($record),
                $meta,
                $actor,
            );

            return $record;
        });
    }
}
