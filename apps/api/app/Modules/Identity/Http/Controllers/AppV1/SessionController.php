<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\SignIn;
use App\Modules\Identity\Actions\SignOut;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /auth/login` (guest, `auth`) → AuthTokenPayload; `POST /auth/logout` → 204 (deletes the
 * current token).
 */
final class SessionController extends IdentityController
{
    public function store(LoginRequest $request, SignIn $signIn): JsonResponse
    {
        $result = $signIn->handle($request->email(), $request->password(), $request->deviceName(), CurrentActor::get());

        return $this->ok(new MeResource($result['user'], $result['token']));
    }

    public function destroy(Request $request, SignOut $signOut): JsonResponse
    {
        $signOut->handle(self::currentUser($request), $request->bearerToken(), CurrentActor::get());

        return $this->noContent();
    }
}
