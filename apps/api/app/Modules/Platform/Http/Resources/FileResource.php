<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Resources;

use App\Support\Files\File;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The File shape of API.md §2.5, for embedding by other modules (attachments, organization
 * profile, invoices, exports):
 *
 *     {"id", "name", "mime_type", "extension", "size_bytes", "download_path", "created_at"}
 *
 * @mixin File
 */
final class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size_bytes' => $this->size_bytes,
            'download_path' => $this->downloadPath(),
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
