<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\RegisterOrganization;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;

/**
 * `POST /auth/register` (guest, `auth`, honeypot): 201 `{email, verification_required,
 * otp_expires_at}`. No token until the e-mail is verified.
 */
final class RegisterController extends IdentityController
{
    public function __invoke(RegisterRequest $request, RegisterOrganization $register): JsonResponse
    {
        $result = $register->handle($request->registrationData(), CurrentActor::get());

        return $this->created([
            'email' => $result['user']->email,
            'verification_required' => true,
            'otp_expires_at' => Iso::format($result['otp']->expires_at),
        ]);
    }
}
