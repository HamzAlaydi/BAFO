<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\AppV1;

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Http\Resources\LegalDocumentResource;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

/**
 * GET /api/app/v1/legal/{code} (guest): the latest published version of a legal document in
 * the request locale. Unknown code or nothing published → 404 not_found.
 */
final class LegalDocumentController extends ApiController
{
    public function show(LegalDocumentCode $code): JsonResponse
    {
        $document = LegalDocument::latestPublished($code, App::getLocale())
            ?? throw (new ModelNotFoundException)->setModel(LegalDocument::class, [$code->value]);

        return $this->ok(new LegalDocumentResource($document))->header('Vary', 'Accept-Language');
    }
}
