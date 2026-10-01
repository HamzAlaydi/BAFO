<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Admin legal documents: publishes a draft version, now or at a given time. From then on it is
 * the version served by GET /legal/{code} and announced by GET /app-config for its locale.
 */
final class PublishLegalDocument
{
    public function handle(LegalDocument $document, Actor $actor, ?CarbonImmutable $at = null): LegalDocument
    {
        return DB::transaction(static function () use ($document, $actor, $at): LegalDocument {
            $locked = LegalDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->published_at !== null) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => 'published']);
            }

            $locked->published_at = $at ?? Date::now();
            $locked->save();

            AuditLogger::log('legal_document.published', $locked, meta: [
                'code' => $locked->code->value,
                'locale' => $locked->locale,
                'version' => $locked->version,
            ], actor: $actor);

            return $locked;
        });
    }
}
