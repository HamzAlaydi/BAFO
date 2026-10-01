<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Resources;

use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Platform\Http\Resources\FileResource;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attachment (API.md §2.7): `{id, kind, title, file, url, is_addendum, sort_order, created_at}`.
 * `file` is the embedded File (§2.5) or null for links; `url` is set for links only.
 *
 * @mixin CompetitionAttachment
 */
final class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'kind' => $this->kind->value,
            'title' => $this->title,
            'file' => $this->file !== null ? (new FileResource($this->file))->resolve($request) : null,
            'url' => $this->url,
            'is_addendum' => $this->is_addendum,
            'sort_order' => $this->sort_order,
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
