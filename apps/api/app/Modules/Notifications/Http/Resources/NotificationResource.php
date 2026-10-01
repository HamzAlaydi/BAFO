<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Services\NotificationRenderer;
use App\Modules\Notifications\Support\Locales;
use App\Modules\Notifications\Support\NotificationRoutes;
use App\Support\Http\Iso;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * `Notification` (API.md §2.11). `title` and `body` are rendered from the stored parameters in
 * the current language: the request's `Accept-Language` on the API, the recipient's language
 * in the `notification.created` broadcast. The id is the row's ULID (§5.8 exception to
 * `public_id`).
 *
 * @property DatabaseNotification $resource
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;
        $data = $notification->getAttribute('data');
        $data = is_array($data) ? $data : [];
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';
        $params = is_array($data['params'] ?? null) ? $data['params'] : [];
        $catalogueType = NotificationType::tryFrom($type);
        $renderer = app(NotificationRenderer::class);
        $locale = Locales::current();

        return [
            'id' => $notification->id,
            'type' => $type,
            'title' => $catalogueType !== null ? $renderer->title($catalogueType, $params, $locale) : $type,
            'body' => $catalogueType !== null ? $renderer->body($catalogueType, $params, $locale) : '',
            'subject' => is_array($data['subject'] ?? null) ? $data['subject'] : null,
            'route' => is_string($data['route'] ?? null) ? $data['route'] : NotificationRoutes::NOTIFICATIONS,
            'params' => (object) $params,
            'read_at' => self::iso($notification->getAttribute('read_at')),
            'created_at' => self::iso($notification->getAttribute('created_at')),
        ];
    }

    private static function iso(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? Iso::format($value) : null;
    }
}
