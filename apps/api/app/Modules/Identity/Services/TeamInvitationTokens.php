<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use Illuminate\Support\Facades\Date;

/**
 * Team invitation tokens (ARCHITECTURE §5.3, §13.10): 40 random bytes in base64url, stored as
 * sha256, valid for 7 days, carried only in the URL fragment of the mail link (D11).
 */
final class TeamInvitationTokens
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The pending, unexpired invitation of this token, or null.
     */
    public function find(string $token, bool $lock = false): ?Membership
    {
        if ($token === '') {
            return null;
        }

        return Membership::query()
            ->where('invite_token_hash', $this->hash($token))
            ->where('status', MembershipStatus::Invited->value)
            ->where('invite_expires_at', '>', Date::now())
            ->when($lock, static fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /**
     * `{WEB_URL}/{locale}/auth/accept-invite#t={token}` (CONVENTIONS §4.3).
     */
    public function acceptUrl(string $token, string $locale): string
    {
        $webUrl = config('bafo.platform.web_url', 'http://localhost:3000');

        return rtrim(is_string($webUrl) ? $webUrl : 'http://localhost:3000', '/').'/'.$locale.'/auth/accept-invite#t='.$token;
    }

    public function ttlDays(): int
    {
        $days = config('bafo.identity.team_invitation_ttl_days', 7);

        return is_numeric($days) ? (int) $days : 7;
    }
}
