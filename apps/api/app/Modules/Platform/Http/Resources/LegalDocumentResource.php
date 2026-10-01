<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\LegalDocument;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /legal/{code} (API.md §1.1). The contract shape has no "id": documents are addressed by
 * code, locale and version.
 *
 * @mixin LegalDocument
 */
final class LegalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code->value,
            'locale' => $this->locale,
            'version' => $this->version,
            'title' => $this->title,
            'body_markdown' => $this->body_markdown,
            'published_at' => Iso::format($this->published_at),
        ];
    }
}
