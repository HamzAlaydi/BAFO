<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum personal access tokens for the first-party apps (D12). The token name is the client's
 * `device_name`; expiry is Sanctum's 90 days (config/sanctum.php).
 */
final class AuthTokens
{
    /**
     * Issues a token and stamps `last_login_at`. Returns the plain-text token (shown once).
     */
    public function issue(User $user, string $deviceName): string
    {
        $user->forceFill(['last_login_at' => Date::now()])->save();

        return $user->createToken(mb_substr($deviceName, 0, 120))->plainTextToken;
    }

    /**
     * Revokes every token of the user, or every token except `$keep`.
     */
    public function revokeAll(User $user, ?int $keepTokenId = null): void
    {
        $user->tokens()
            ->when($keepTokenId !== null, static fn ($query) => $query->whereKeyNot($keepTokenId))
            ->delete();
    }

    /**
     * The stored token of the user that matches the request's bearer token, or null.
     */
    public function findForUser(User $user, ?string $bearerToken): ?PersonalAccessToken
    {
        if ($bearerToken === null || $bearerToken === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        return $token !== null
            && $token->tokenable_type === $user->getMorphClass()
            && (int) $token->tokenable_id === $user->id ? $token : null;
    }
}
