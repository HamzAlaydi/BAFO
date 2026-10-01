<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Data\PushMessage;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Services\NotificationRenderer;
use App\Modules\Notifications\Support\Locales;
use App\Modules\Notifications\Support\NotificationRoutes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Base of the 31 catalogue notifications (ARCHITECTURE §11.1). Each subclass only names its
 * catalogue type; everything else is shared:
 *
 * - `via()` is the catalogue row minus the channels excluded for this recipient;
 * - `toDatabase()` stores `{type, params, subject, route}` (§11.2); titles and bodies are
 *   rendered at read time in the request language;
 * - `toMail()` and `toPush()` render in the recipient's language (Laravel switches to
 *   `users.locale` through `HasLocalePreference` before calling them).
 *
 * One instance is built per recipient (`NotificationDispatcher`), because the constructor sets
 * the row id: a lowercase ULID in `notifications.id` char(26). Sending one instance to several
 * users would reuse the id and violate the primary key.
 */
abstract class BafoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $params
     * @param  list<DeliveryChannel>  $without  catalogue channels that do not apply to this recipient
     */
    final public function __construct(
        public array $params,
        public NotificationSubject $subject,
        public string $route,
        public array $without = [],
    ) {
        $this->id = strtolower((string) Str::ulid());
        $this->onQueue('notifications');
    }

    /**
     * @param  list<DeliveryChannel>  $without
     */
    public static function fromPayload(NotificationPayload $payload, array $without = []): static
    {
        return new static($payload->params, $payload->subject, $payload->route, $without);
    }

    abstract public static function type(): NotificationType;

    /**
     * @return list<DeliveryChannel>
     */
    public function channels(): array
    {
        return array_values(array_filter(
            static::type()->channels(),
            fn (DeliveryChannel $channel): bool => ! in_array($channel, $this->without, true),
        ));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_map(static fn (DeliveryChannel $channel): string => $channel->value, $this->channels());
    }

    /**
     * @return array{type: string, params: array<string, mixed>, subject: array{type: string, id: string}, route: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => static::type()->value,
            'params' => $this->params,
            'subject' => $this->subject->toArray(),
            'route' => $this->route,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = Locales::current();
        $mail = self::renderer()->mail(static::type(), $this->params, $locale);

        return (new MailMessage)
            ->subject($mail->subject)
            ->markdown('notifications::mail.notification', [
                'greeting' => self::line('notifications.mail.greeting', [
                    'name' => $notifiable instanceof User ? $notifiable->name : '',
                ], $locale),
                'intro' => $mail->intro,
                'lines' => $this->mailLines($locale),
                'actionText' => $mail->action,
                'actionUrl' => NotificationRoutes::webUrl($this->route, $locale),
                'salutation' => self::line('notifications.mail.salutation', [], $locale),
                'signature' => self::line('notifications.mail.signature', [], $locale),
            ]);
    }

    /**
     * Title and body only: never amounts, ranks or other participants' identities (§11.1).
     */
    public function toPush(object $notifiable): PushMessage
    {
        $locale = Locales::current();
        $renderer = self::renderer();

        return new PushMessage(
            title: $renderer->title(static::type(), $this->params, $locale),
            body: $renderer->body(static::type(), $this->params, $locale),
            data: [
                'type' => static::type()->value,
                'notification_id' => (string) $this->id,
                'subject_type' => $this->subject->type,
                'subject_id' => $this->subject->id,
                'route' => $this->route,
            ],
        );
    }

    /**
     * Extra mail paragraphs after the intro, already localised.
     *
     * @return list<string>
     */
    protected function mailLines(string $locale): array
    {
        return [];
    }

    /**
     * A translated line, or null when the key is missing.
     *
     * @param  array<string, string>  $replace
     */
    protected static function optionalLine(string $key, array $replace, string $locale): ?string
    {
        $line = __($key, $replace, $locale);

        return is_string($line) && $line !== $key ? $line : null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected static function line(string $key, array $replace, string $locale): string
    {
        return self::optionalLine($key, $replace, $locale) ?? '';
    }

    private static function renderer(): NotificationRenderer
    {
        return app(NotificationRenderer::class);
    }
}
