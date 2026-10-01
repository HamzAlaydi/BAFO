<?php

declare(strict_types=1);

use App\Modules\Bidding\Data\OfferAcceptedContext;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Notifications\Broadcasting\NotificationCreatedBroadcast;
use App\Modules\Notifications\Contracts\PushNotifier;
use App\Modules\Notifications\Data\PushMessage;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Notifications\CompetitionClosedNotification;
use App\Modules\Notifications\Services\CompetitionNotifier;
use App\Modules\Notifications\Services\LogPushNotifier;
use App\Modules\Notifications\Support\SampleNotifications;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Monolog\Handler\TestHandler;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Support\Notifications\NotificationScenario as S;
use Tests\Support\Notifications\RecordingPushNotifier;

/*
 * The delivery mechanics of ARCHITECTURE §11.1 with real channels: in-app rows, the realtime
 * follow-up, push through PushNotifier and mail with the BAFO theme.
 */

beforeEach(function () {
    $this->issuer = S::team();
    $this->issuer->admin->update(['locale' => 'en']);
    $this->competition = S::competition($this->issuer->organization, fn ($f) => $f->withoutFinalWindow()->live(), ['title' => 'توريد خوادم']);
});

/**
 * @return list<Email>
 */
function sentMails(): array
{
    return array_values(app('mailer')->getSymfonyTransport()->messages()
        ->map(static fn (SentMessage $message): Email => $message->getOriginalMessage())
        ->all());
}

it('stores one row per recipient, each with its own lowercase ULID, in the §11.2 shape', function () {
    app(CompetitionNotifier::class)->closed($this->competition);

    $rows = DatabaseNotification::query()->orderBy('notifiable_id')->get();
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('id')->unique())->toHaveCount(3)
        ->and($rows->pluck('notifiable_id')->all())->toBe(S::ids($this->issuer->active()));

    $row = $rows->first();
    expect($row->id)->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/')
        ->and($row->getAttribute('type'))->toBe(CompetitionClosedNotification::class)
        ->and($row->getAttribute('notifiable_type'))->toBe('user')
        ->and($row->getAttribute('data'))->toEqual([
            'type' => 'competition.closed',
            'params' => ['competition_title' => 'توريد خوادم', 'direction' => 'tender'],
            'subject' => ['type' => 'competition', 'id' => $this->competition->public_id],
            'route' => '/competitions/'.$this->competition->public_id,
        ]);
});

it('queues every channel on the notifications queue', function () {
    Queue::fake();

    app(CompetitionNotifier::class)->closed($this->competition);

    // 3 issuer users × (database, push, mail).
    Queue::assertPushedOn('notifications', SendQueuedNotifications::class);
    Queue::assertPushed(SendQueuedNotifications::class, 9);
});

it('follows each in-app row with notification.created on the user channel, in the user\'s language', function () {
    Event::fake([NotificationCreatedBroadcast::class]);

    app(CompetitionNotifier::class)->closed($this->competition);

    Event::assertDispatchedTimes(NotificationCreatedBroadcast::class, 3);
    Event::assertDispatched(NotificationCreatedBroadcast::class, function (NotificationCreatedBroadcast $event): bool {
        if ($event->userPublicId !== $this->issuer->admin->public_id) {
            return false;
        }

        $row = DatabaseNotification::query()->where('notifiable_id', $this->issuer->admin->id)->sole();

        return $event->broadcastOn()[0]->name === 'private-user.'.$this->issuer->admin->public_id
            && $event->broadcastAs() === 'notification.created'
            && $event->broadcastWith()['unread_count'] === 1
            && $event->broadcastWith()['notification']['id'] === $row->id
            && $event->broadcastWith()['notification']['title'] === 'Competition closed'
            && $event->queue === 'live';
    });
    Event::assertDispatched(NotificationCreatedBroadcast::class, fn (NotificationCreatedBroadcast $event): bool => $event->userPublicId === $this->issuer->owner->public_id
        && $event->broadcastWith()['notification']['title'] === 'أُغلقت المنافسة');
});

it('pushes to the recipient\'s devices in the recipient\'s language, with the deep-link data as strings', function () {
    $push = RecordingPushNotifier::swap();
    $phone = DeviceToken::factory()->create(['user_id' => $this->issuer->admin->id]);
    $tablet = DeviceToken::factory()->ios()->create(['user_id' => $this->issuer->admin->id]);

    app(CompetitionNotifier::class)->closed($this->competition);

    expect($push->sent)->toHaveCount(1); // the other users have no device

    $row = DatabaseNotification::query()->where('notifiable_id', $this->issuer->admin->id)->sole();
    $message = $push->sent[0]['message'];
    expect($push->sent[0]['tokens'])->toEqualCanonicalizing([$phone->token, $tablet->token])
        ->and($message->title)->toBe('Competition closed')
        ->and($message->body)->toBe('Offers are closed for “توريد خوادم”.')
        ->and($message->data)->toBe([
            'type' => 'competition.closed',
            'notification_id' => $row->id,
            'subject_type' => 'competition',
            'subject_id' => $this->competition->public_id,
            'route' => '/competitions/'.$this->competition->public_id,
        ]);
});

it('logs pushes with the log driver, without the device tokens', function () {
    config(['logging.channels.push' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
    $device = DeviceToken::factory()->create();

    expect(app(PushNotifier::class))->toBeInstanceOf(LogPushNotifier::class);
    app(PushNotifier::class)->send(DeviceToken::query()->get(), new PushMessage('عنوان', 'نص', [
        'type' => 'competition.opened', 'notification_id' => 'n', 'subject_type' => 'competition', 'subject_id' => 's', 'route' => '/competitions/s/live',
    ]));

    /** @var TestHandler $handler */
    $handler = Log::channel('push')->getLogger()->getHandlers()[0];
    $record = $handler->getRecords()[0];
    expect($record->message)->toBe('push.sent')
        ->and($record->context)->toMatchArray(['title' => 'عنوان', 'body' => 'نص', 'token_count' => 1, 'platforms' => ['android' => 1]])
        ->and(json_encode($record->context))->not->toContain($device->token);
});

it('mails in the recipient\'s language with the branded layout: right to left in Arabic, left to right in English', function () {
    app(CompetitionNotifier::class)->closed($this->competition);

    $mails = collect(sentMails())->keyBy(static fn (Email $mail): string => $mail->getTo()[0]->getAddress());
    $arabic = $mails[$this->issuer->owner->email];
    $english = $mails[$this->issuer->admin->email];

    expect($mails)->toHaveCount(3)
        ->and($arabic->getSubject())->toBe('أُغلقت المنافسة: توريد خوادم')
        ->and($arabic->getHtmlBody())->toContain('<html xmlns="http://www.w3.org/1999/xhtml" lang="ar" dir="rtl">')
        ->toContain('dir="rtl" align="right"')
        ->toContain('text-align: right')
        ->toContain('مرحباً '.$this->issuer->owner->name.'،')
        ->toContain('http://localhost:8000/mail/brand/bafo-mark.png')
        ->toContain('href="http://localhost:3000/ar/dashboard/competitions/'.$this->competition->public_id.'"')
        ->toContain('مراجعة العروض')
        ->and($english->getSubject())->toBe('Competition closed: توريد خوادم')
        ->and($english->getHtmlBody())->toContain('lang="en" dir="ltr"')
        ->toContain('text-align: left')
        ->toContain('Hello '.$this->issuer->admin->name.',')
        ->toContain('href="http://localhost:3000/en/dashboard/competitions/'.$this->competition->public_id.'"')
        ->and($english->getTextBody())->toContain('Offers are closed for “توريد خوادم”.')
        ->not->toContain('<p>');
});

it('keeps offer amounts, ranks and other participants out of push, mail and in-app data', function () {
    $push = RecordingPushNotifier::swap();
    $winner = S::participant($this->competition);
    $loser = S::participant($this->competition);
    foreach ([$winner, $loser] as $participant) {
        foreach (S::usersOf($participant) as $user) {
            DeviceToken::factory()->create(['user_id' => $user->id]);
        }
    }
    DeviceToken::factory()->create(['user_id' => $this->issuer->owner->id]);

    $losing = Offer::factory()->amount(8_765_400)->create(['participant_id' => $loser->id]);
    $winning = Offer::factory()->amount(7_654_300)->create(['participant_id' => $winner->id]);
    S::fire(S::BIDDING.'OfferAccepted', [
        'offer' => $winning, 'competition' => $this->competition,
        'context' => new OfferAcceptedContext(true, true, $loser->id, [$winner->id, $loser->id], false, 2),
    ]);
    $this->competition->update(['status' => 'awarded']);
    $award = Award::factory()->create(['offer_id' => $winning->id, 'awarded_by_user_id' => $this->issuer->owner->id]);
    S::fire(S::BIDDING.'AwardIssued', ['award' => $award, 'competition' => $this->competition, 'actor' => S::actor()]);

    $pushes = json_encode(array_map(static fn (array $sent): array => (array) $sent['message'], $push->sent), JSON_UNESCAPED_UNICODE);
    $rows = DatabaseNotification::query()->get()->map(static fn (DatabaseNotification $row): mixed => $row->getAttribute('data'))->toJson(JSON_UNESCAPED_UNICODE);
    $mails = implode(' ', array_map(static fn (Email $mail): string => (string) $mail->getHtmlBody(), sentMails()));

    expect($push->sent)->not->toBeEmpty();
    foreach ([$pushes, $rows, $mails] as $payload) {
        expect($payload)
            ->not->toContain('87654')->not->toContain('87,654')
            ->not->toContain('76543')->not->toContain('76,543')
            ->not->toContain($winner->organization->name)
            ->not->toContain($loser->organization->name)
            ->not->toContain('rank');
    }
});

it('renders every catalogue type in both languages without leaving a placeholder', function (NotificationType $type, string $locale) {
    $class = $type->notificationClass();
    $notification = $class::fromPayload(SampleNotifications::payload($type));
    $user = S::team($locale)->owner;
    app()->setLocale($locale);

    $message = $notification->toPush($user);
    $texts = [$message->title, $message->body];

    if ($type->sends(DeliveryChannel::Mail)) {
        $texts[] = (string) $notification->toMail($user)->render();
    }

    foreach ($texts as $text) {
        expect($text)->not->toBe('')
            ->not->toContain('notifications.')
            ->not->toMatch('/:(competition_title|competition_type|issuer_name|close_time|cutoff_time|minutes|reason|organization_name|plan_name|ends_at|days_left|number|unused_count|code|amount|url|created|updated|errors)\\b/');
    }
})->with(NotificationType::cases())->with(['ar', 'en']);
