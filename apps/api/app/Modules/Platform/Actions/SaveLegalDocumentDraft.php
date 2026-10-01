<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Admin legal documents: creates a draft version or edits one. Published versions are
 * immutable (consents point at them); publish a new version instead.
 */
final class SaveLegalDocumentDraft
{
    /**
     * @param  array{code?: LegalDocumentCode|string, locale?: string, version?: string, title?: string, body_markdown?: string}  $attributes
     */
    public function handle(?LegalDocument $document, array $attributes, Actor $actor): LegalDocument
    {
        return DB::transaction(static function () use ($document, $attributes, $actor): LegalDocument {
            if ($document?->published_at !== null) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => 'published']);
            }

            $creating = $document === null;
            $document ??= new LegalDocument(['created_by_admin_id' => $actor->adminId]);
            $document->fill($attributes);
            $document->save();

            AuditLogger::log(
                $creating ? 'legal_document.created' : 'legal_document.updated',
                $document,
                $creating ? [] : AuditLogger::diff($document, ['code', 'locale', 'version', 'title']),
                actor: $actor,
            );

            return $document;
        });
    }
}
