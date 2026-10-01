<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Platform\Http\Resources\FileResource;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `ExportJob` (API.md §2.12). Download `file` through `/files/{file}/download` once completed.
 *
 * @mixin ExportJob
 */
final class ExportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('file');

        return [
            'id' => $this->public_id,
            'type' => $this->type->value,
            'format' => $this->format->value,
            'filters' => (object) $this->filters,
            'status' => $this->status->value,
            'row_count' => $this->row_count,
            'file' => $this->file === null ? null : (new FileResource($this->file))->resolve($request),
            'failure_message' => $this->failure_message,
            'finished_at' => Iso::format($this->finished_at),
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
