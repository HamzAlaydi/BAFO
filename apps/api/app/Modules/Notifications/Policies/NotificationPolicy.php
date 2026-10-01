<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Services\NotificationInbox;
use Illuminate\Auth\Access\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notifications belong to their recipient only. Another user's notification answers
 * 404, so its existence is never revealed (CONVENTIONS §2.3).
 */
final readonly class NotificationPolicy
{
    public function __construct(private NotificationInbox $inbox) {}

    public function update(User $user, DatabaseNotification $notification): Response
    {
        return $this->owner($user, $notification);
    }

    public function delete(User $user, DatabaseNotification $notification): Response
    {
        return $this->owner($user, $notification);
    }

    private function owner(User $user, DatabaseNotification $notification): Response
    {
        return $this->inbox->owns($user, $notification) ? Response::allow() : Response::denyAsNotFound();
    }
}
