<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\AppV1;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\DeleteAllNotifications;
use App\Modules\Notifications\Actions\DeleteNotification;
use App\Modules\Notifications\Actions\MarkAllNotificationsRead;
use App\Modules\Notifications\Actions\MarkNotificationRead;
use App\Modules\Notifications\Http\Requests\ListNotificationsRequest;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Services\NotificationInbox;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notifications of the signed-in user (API.md §1.8).
 */
final class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationInbox $inbox) {}

    /** `GET /notifications`: newest first, `?unread=1` for unread only, plus `meta.unread_count`. */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $user = self::user($request);
        $query = $this->inbox->query($user);

        if ($request->unreadOnly()) {
            $query->whereNull('read_at');
        }

        return $this->paginated(
            $query->paginate($request->perPage()),
            NotificationResource::class,
            ['unread_count' => $this->inbox->unreadCount($user)],
        );
    }

    /** `GET /notifications/unread-count` */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['unread_count' => $this->inbox->unreadCount(self::user($request))]);
    }

    /** `POST /notifications/{notification}/read` */
    public function read(Request $request, DatabaseNotification $notification, MarkNotificationRead $action): JsonResponse
    {
        $this->authorize('update', $notification);

        return $this->ok(NotificationResource::make($action->handle(self::user($request), $notification)));
    }

    /** `POST /notifications/read-all` */
    public function readAll(Request $request, MarkAllNotificationsRead $action): JsonResponse
    {
        return $this->ok(['unread_count' => $action->handle(self::user($request))]);
    }

    /** `DELETE /notifications/{notification}` */
    public function destroy(Request $request, DatabaseNotification $notification, DeleteNotification $action): JsonResponse
    {
        $this->authorize('delete', $notification);

        $action->handle(self::user($request), $notification);

        return $this->noContent();
    }

    /** `DELETE /notifications` */
    public function destroyAll(Request $request, DeleteAllNotifications $action): JsonResponse
    {
        $action->handle(self::user($request));

        return $this->noContent();
    }

    private static function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
