<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Services\OtpService;

/**
 * `POST /auth/otp/check` (ARCHITECTURE §13.9): validates a password reset code early, without
 * consuming it. A wrong code still counts as an attempt.
 */
final readonly class CheckOtpCode
{
    public function __construct(private OtpService $otp) {}

    public function handle(string $email, OtpPurpose $purpose, string $code): void
    {
        $this->otp->check($email, $purpose, $code);
    }
}
