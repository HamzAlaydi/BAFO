<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\ChangePassword;
use App\Modules\Identity\Actions\ReplaceUserAvatar;
use App\Modules\Identity\Actions\UpdateProfile;
use App\Modules\Identity\Http\Requests\UpdateMeRequest;
use App\Modules\Identity\Http\Requests\UpdatePasswordRequest;
use App\Modules\Identity\Http\Requests\UploadFileRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user (API.md §1.3):
 *
 *   GET /me              Me
 *   PATCH /me            name, phone, locale → Me
 *   PUT /me/password     204; revokes every other token
 *   POST /me/avatar      multipart `file` → Me
 *   DELETE /me/avatar    Me
 */
final class MeController extends IdentityController
{
    public function show(Request $request): JsonResponse
    {
        return $this->ok(new MeResource(self::currentUser($request)));
    }

    public function update(UpdateMeRequest $request, UpdateProfile $update): JsonResponse
    {
        $user = $update->handle(self::currentUser($request), $request->profile(), CurrentActor::get());

        return $this->ok(new MeResource($user));
    }

    public function password(UpdatePasswordRequest $request, ChangePassword $change): JsonResponse
    {
        $change->handle(self::currentUser($request), $request->currentPassword(), $request->newPassword(), $request->bearerToken(), CurrentActor::get());

        return $this->noContent();
    }

    public function storeAvatar(UploadFileRequest $request, ReplaceUserAvatar $replace): JsonResponse
    {
        $user = $replace->handle(self::currentUser($request), $request->upload(), CurrentActor::get());

        return $this->ok(new MeResource($user));
    }

    public function destroyAvatar(Request $request, ReplaceUserAvatar $replace): JsonResponse
    {
        $user = $replace->handle(self::currentUser($request), null, CurrentActor::get());

        return $this->ok(new MeResource($user));
    }
}
