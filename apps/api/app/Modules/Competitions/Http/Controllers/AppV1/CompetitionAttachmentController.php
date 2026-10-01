<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Actions\AddAttachment;
use App\Modules\Competitions\Actions\DeleteAttachment;
use App\Modules\Competitions\Actions\UpdateAttachment;
use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Http\Requests\StoreAttachmentRequest;
use App\Modules\Competitions\Http\Requests\UpdateAttachmentRequest;
use App\Modules\Competitions\Http\Resources\AttachmentResource;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Competition documents and links (API.md §1.4). Visibility by kind (ARCHITECTURE §8.5): the
 * issuer and joined participants see everything; invitees only `invitation_document`.
 */
final class CompetitionAttachmentController extends ApiController
{
    use ActsForCaller;

    public function index(Competition $competition, ViewerResolver $viewers): JsonResponse
    {
        $this->authorize('view', $competition);

        $viewer = $viewers->for($competition, $this->actor());

        $attachments = CompetitionAttachment::query()
            ->with('file')
            ->where('competition_id', $competition->id)
            ->when($viewer->isInvitee(), static fn ($q) => $q->where('kind', AttachmentKind::InvitationDocument->value))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->ok(AttachmentResource::collection($attachments));
    }

    public function store(StoreAttachmentRequest $request, Competition $competition, AddAttachment $add): JsonResponse
    {
        $this->authorize('manage', $competition);

        $attachment = $add->handle($competition, $request->kind(), $request->uploadedFile(), $request->url(), $request->title(), $this->actor());

        return $this->created(new AttachmentResource($attachment));
    }

    public function update(UpdateAttachmentRequest $request, Competition $competition, CompetitionAttachment $attachment, UpdateAttachment $update): JsonResponse
    {
        $this->authorize('manage', $competition);

        return $this->ok(new AttachmentResource($update->handle($attachment, $request->changes(), $this->actor())));
    }

    public function destroy(Competition $competition, CompetitionAttachment $attachment, DeleteAttachment $delete): JsonResponse
    {
        $this->authorize('manage', $competition);

        $delete->handle($attachment, $this->actor());

        return $this->noContent();
    }
}
