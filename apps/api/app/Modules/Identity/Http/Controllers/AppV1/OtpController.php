<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\CheckOtpCode;
use App\Modules\Identity\Actions\SendOtpCode;
use App\Modules\Identity\Actions\VerifyEmail;
use App\Modules\Identity\Http\Requests\CheckOtpRequest;
use App\Modules\Identity\Http\Requests\SendOtpRequest;
use App\Modules\Identity\Http\Requests\VerifyOtpRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;

/**
 * The OTP endpoints (guest, `auth`), API.md §1.3:
 *
 *   POST /auth/otp/send     202 {otp_expires_at}; the same answer for unknown e-mails
 *   POST /auth/otp/verify   AuthTokenPayload (e-mail verification)
 *   POST /auth/otp/check    {valid: true} without consuming (password reset)
 */
final class OtpController extends IdentityController
{
    public function send(SendOtpRequest $request, SendOtpCode $send): JsonResponse
    {
        $expiresAt = $send->handle($request->email(), $request->purpose(), CurrentActor::get());

        return $this->ok(['otp_expires_at' => Iso::format($expiresAt)], status: 202);
    }

    public function verify(VerifyOtpRequest $request, VerifyEmail $verify): JsonResponse
    {
        $result = $verify->handle($request->email(), $request->code(), $request->deviceName(), CurrentActor::get());

        return $this->ok(new MeResource($result['user'], $result['token']));
    }

    public function check(CheckOtpRequest $request, CheckOtpCode $check): JsonResponse
    {
        $check->handle($request->email(), $request->purpose(), $request->code());

        return $this->ok(['valid' => true]);
    }
}
