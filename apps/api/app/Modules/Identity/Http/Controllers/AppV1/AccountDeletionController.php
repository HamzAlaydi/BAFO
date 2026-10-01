<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\CancelAccountDeletion;
use App\Modules\Identity\Actions\RequestAccountDeletion;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Http\Requests\StoreAccountDeletionRequest;
use App\Modules\Identity\Http\Resources\AccountDeletionRequestResource;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account deletion (store requirement), API.md §1.3 and ARCHITECTURE §13.8. Exempt from the
 * account gate, so a suspended organization can still ask to be deleted.
 *
 *   POST /account/deletion     201 AccountDeletionRequest
 *   GET /account/deletion      the pending request, or null
 *   DELETE /account/deletion   204 (cancels it)
 */
final class AccountDeletionController extends IdentityController
{
    public function store(StoreAccountDeletionRequest $request, RequestAccountDeletion $requestDeletion): JsonResponse
    {
        $deletion = $requestDeletion->handle(self::currentUser($request), $request->password(), $request->reason(), $request->bearerToken(), CurrentActor::get());

        return $this->created(new AccountDeletionRequestResource($deletion));
    }

    public function show(Request $request): JsonResponse
    {
        $deletion = AccountDeletionRequest::query()
            ->where('user_id', self::currentUser($request)->id)
            ->where('status', DeletionStatus::Pending->value)
            ->first();

        return $this->ok($deletion !== null ? new AccountDeletionRequestResource($deletion) : null);
    }

    public function destroy(Request $request, CancelAccountDeletion $cancel): JsonResponse
    {
        $cancel->handle(self::currentUser($request), CurrentActor::get());

        return $this->noContent();
    }
}
