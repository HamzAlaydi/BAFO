<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\Web;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\Locales;
use App\Modules\Notifications\Support\SampleNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Traits\Localizable;

/**
 * Local mail preview (registered in the `local` environment only): `GET /dev/mail` lists the
 * catalogue types that send mail, `GET /dev/mail/{type}?locale=ar|en` renders one with sample
 * data through the real notification, layout and theme.
 */
final class MailPreviewController
{
    use Localizable;

    public function index(Request $request): View
    {
        $base = rtrim($request->url(), '/');
        $links = [];

        foreach (NotificationType::cases() as $type) {
            if ($type->sends(DeliveryChannel::Mail)) {
                $links[$type->value] = [
                    'ar' => $base.'/'.$type->value.'?locale=ar',
                    'en' => $base.'/'.$type->value.'?locale=en',
                ];
            }
        }

        return view('notifications::preview.index', ['links' => $links]);
    }

    public function show(Request $request, string $type): Response
    {
        $notificationType = NotificationType::tryFrom($type);

        abort_if($notificationType === null || ! $notificationType->sends(DeliveryChannel::Mail), 404);

        $locale = Locales::normalize($request->query('locale') === null ? 'ar' : $request->string('locale')->toString());

        $html = $this->withLocale($locale, static function () use ($notificationType, $locale): string {
            $class = $notificationType->notificationClass();
            $name = __('notifications.preview.recipient_name');
            $recipient = new User(['name' => is_string($name) ? $name : '', 'locale' => $locale]);

            return (string) $class::fromPayload(SampleNotifications::payload($notificationType))->toMail($recipient)->render();
        });

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
