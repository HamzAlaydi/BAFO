<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\RequestPasswordReset;
use App\Modules\Identity\Actions\ResetPassword;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;

/**
 * Password reset by OTP (guest, `auth`), ARCHITECTURE §13.9:
 *
 *   POST /auth/password/forgot   always 202 {}
 *   POST /auth/password/reset    204; every token of the user is revoked
 */
final class PasswordResetController extends IdentityController
{
    public function forgot(ForgotPasswordRequest $request, RequestPasswordReset $forgot): JsonResponse
    {
        $forgot->handle($request->email(), CurrentActor::get());

        return $this->ok((object) [], status: 202);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $reset): JsonResponse
    {
        $reset->handle($request->email(), $request->code(), $request->password(), CurrentActor::get());

        return $this->noContent();
    }
}
