<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Broadcasting;

use App\Modules\Identity\Models\User;

/**
 * `private-user.{userPublicId}` (ARCHITECTURE §9.2): the user's own notification channel.
 * Authorised when the id is the authenticated user's public id (any case).
 */
final class UserChannel
{
    public const string NAME = 'user.{userPublicId}';

    public static function for(User $user): string
    {
        return 'user.'.$user->public_id;
    }

    public function join(User $user, string $userPublicId): bool
    {
        return $user->public_id === strtolower($userPublicId);
    }
}
