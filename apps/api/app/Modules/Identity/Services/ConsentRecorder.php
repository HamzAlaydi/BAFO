<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Records the acceptance of the latest published legal documents (ARCHITECTURE §5.3 `consents`,
 * §13.9).
 */
final class ConsentRecorder
{
    /**
     * @param  list<LegalDocumentCode>  $codes
     */
    public function record(User $user, ?int $organizationId, array $codes, string $locale, Actor $actor): void
    {
        foreach ($codes as $code) {
            // CONTRACT-GAP: the version is the latest published one in the user's locale, else in
            // any locale; with nothing published (a fresh database without the Platform seeder)
            // no row is written, because there is no version to accept.
            $document = LegalDocument::latestPublished($code, $locale)
                ?? LegalDocument::latestPublished($code, $locale === 'ar' ? 'en' : 'ar');

            if ($document === null) {
                Log::warning('No published legal document to record consent against.', [
                    'document_code' => $code->value,
                    'user_id' => $user->id,
                ]);

                continue;
            }

            Consent::query()->create([
                'user_id' => $user->id,
                'organization_id' => $organizationId,
                'document_code' => $code,
                'document_version' => $document->version,
                'locale' => $document->locale,
                'accepted_at' => Date::now(),
                'ip' => $actor->ip,
                'user_agent' => $actor->userAgent,
            ]);
        }
    }
}
