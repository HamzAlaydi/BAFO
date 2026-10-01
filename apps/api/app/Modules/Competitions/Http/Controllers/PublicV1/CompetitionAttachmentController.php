<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\PublicV1;

use App\Modules\Competitions\Actions\AddAttachment;
use App\Modules\Competitions\Actions\DeleteAttachment;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForApiClient;
use App\Modules\Competitions\Http\Requests\StoreAttachmentRequest;
use App\Modules\Competitions\Http\Resources\AttachmentResource;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Public API attachments (API.md §3.4): list, add (multipart file or JSON link), delete (draft or
 * scheduled only).
 */
final class CompetitionAttachmentController extends ApiController
{
    use ActsForApiClient;

    public function index(Competition $competition): JsonResponse
    {
        $this->owned($competition);

        $attachments = CompetitionAttachment::query()->with('file')
            ->where('competition_id', $competition->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return $this->ok(AttachmentResource::collection($attachments));
    }

    public function store(StoreAttachmentRequest $request, Competition $competition, AddAttachment $add): JsonResponse
    {
        $this->owned($competition);

        $attachment = $add->handle($competition, $request->kind(), $request->uploadedFile(), $request->url(), $request->title(), $this->actor());

        return $this->created(new AttachmentResource($attachment));
    }

    public function destroy(Competition $competition, CompetitionAttachment $attachment, DeleteAttachment $delete): JsonResponse
    {
        $this->owned($competition);

        $delete->handle($attachment, $this->actor());

        return $this->noContent();
    }
}
