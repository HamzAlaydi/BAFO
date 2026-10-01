<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Resources;

use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Platform\Http\Resources\FileResource;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `ImportJob` (API.md §2.12). `errors_file` is null when every row was valid.
 *
 * @mixin ImportJob
 */
final class ImportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['sourceFile', 'errorsFile']);

        return [
            'id' => $this->public_id,
            'type' => $this->type->value,
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'total_rows' => $this->total_rows,
            'valid_rows' => $this->valid_rows,
            'created_rows' => $this->created_rows,
            'updated_rows' => $this->updated_rows,
            'error_rows' => $this->error_rows,
            'errors_preview' => $this->errors_preview ?? [],
            'errors_file' => $this->errorsFile === null ? null : (new FileResource($this->errorsFile))->resolve($request),
            'source_file' => $this->sourceFile === null ? null : (new FileResource($this->sourceFile))->resolve($request),
            'failure_message' => $this->failure_message,
            'finished_at' => Iso::format($this->finished_at),
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
