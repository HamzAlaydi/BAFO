<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Auth\Access\Response;

/**
 * A device registration belongs to its user; another user's device answers 404.
 */
final class DeviceTokenPolicy
{
    public function delete(User $user, DeviceToken $device): Response
    {
        return $device->user_id === $user->getKey() ? Response::allow() : Response::denyAsNotFound();
    }
}
