<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Resources;

use App\Modules\Bidding\Enums\ReportStatus;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Platform\Http\Resources\FileResource;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `Report` (API.md §2.9): `{"status", "locale", "generated_at", "file"}`; the file is set only
 * when the report is ready.
 *
 * @mixin CompetitionReport
 */
final class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ready = $this->status === ReportStatus::Ready && $this->file !== null;

        return [
            'status' => $this->status->value,
            'locale' => $this->locale,
            'generated_at' => $ready ? Iso::format($this->generated_at) : null,
            'file' => $ready ? (new FileResource($this->file))->resolve($request) : null,
        ];
    }
}
