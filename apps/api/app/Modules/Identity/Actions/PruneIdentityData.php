<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\OtpCode;
use Illuminate\Support\Facades\Date;

/**
 * `identity:prune` (ARCHITECTURE §12, daily): deletes OTP codes older than 24 h and spends
 * expired team invitation tokens (the token is nulled; the membership stays `invited`, so the
 * owner can resend it).
 *
 * CONTRACT-GAP: housekeeping only; no audit entry (nothing a user did).
 */
final class PruneIdentityData
{
    /**
     * @return array{otp_codes: int, invite_tokens: int}
     */
    public function handle(): array
    {
        $now = Date::now();

        $otpCodes = OtpCode::query()->where('created_at', '<', $now->subDay())->delete();

        $inviteTokens = Membership::query()
            ->where('status', MembershipStatus::Invited->value)
            ->whereNotNull('invite_token_hash')
            ->where('invite_expires_at', '<=', $now)
            ->update(['invite_token_hash' => null]);

        return ['otp_codes' => (int) $otpCodes, 'invite_tokens' => $inviteTokens];
    }
}
