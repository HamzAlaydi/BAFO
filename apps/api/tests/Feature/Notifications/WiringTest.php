<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UserChannel;
use App\Modules\Notifications\Channels\PushChannel;
use App\Modules\Notifications\Contracts\PushNotifier;
use App\Modules\Notifications\Http\Controllers\Web\MailPreviewController;
use App\Modules\Notifications\Listeners\BroadcastNewNotification;
use App\Modules\Notifications\Listeners\QueuedNotificationListener;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Notifications\Services\LogPushNotifier;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\Notifications\NotificationScenario as S;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

/*
 * Module wiring: listeners bound by event class-string (§3.4, §10), queues (§4.10), the push
 * driver and channel (§15.1), the user channel (§9.2), the mail logo and preview routes, and
 * the weekly device prune (§12).
 */

it('binds a queued, after-commit listener to every §10 event it reacts to', function (string $event, string $listener) {
    Event::fake();

    Event::assertListening($event, $listener);

    $reflection = new ReflectionClass($listener);
    $defaults = $reflection->getDefaultProperties();
    expect($reflection->isSubclassOf(QueuedNotificationListener::class))->toBeTrue()
        ->and($reflection->implementsInterface(ShouldQueueAfterCommit::class))->toBeTrue()
        ->and($reflection->implementsInterface(ShouldHandleEventsAfterCommit::class))->toBeTrue()
        ->and($defaults['queue'])->toBe('notifications')
        ->and($defaults['tries'])->toBe(3);
})->with(fn (): array => array_map(
    static fn (string $event, string $listener): array => [$event, $listener],
    array_keys(NotificationsServiceProvider::EVENT_LISTENERS),
    array_values(NotificationsServiceProvider::EVENT_LISTENERS),
));

it('covers the 31 catalogue reactions of §10 plus the device clean-up', function () {
    expect(NotificationsServiceProvider::EVENT_LISTENERS)->toHaveCount(31);

    Event::fake();
    Event::assertListening(NotificationSent::class, BroadcastNewNotification::class);
});

it('puts listener jobs on the notifications queue', function () {
    Queue::fake();
    $competition = S::competition(S::team()->organization);

    S::fire(S::COMPETITIONS.'CompetitionOpened', ['competition' => $competition]);

    Queue::assertPushedOn('notifications', CallQueuedListener::class);
});

it('uses the log push driver by default and refuses an unknown one', function () {
    expect(app(PushNotifier::class))->toBeInstanceOf(LogPushNotifier::class)
        ->and(app(ChannelManager::class)->driver('push'))->toBeInstanceOf(PushChannel::class);

    config(['bafo.notifications.push.driver' => 'firebase']);
    app()->forgetInstance(PushNotifier::class);

    expect(fn () => app(PushNotifier::class))->toThrow(InvalidArgumentException::class, 'Unknown push driver [firebase]');
});

it('authorizes the user channel for its owner only', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'notifications-key',
            'secret' => 'notifications-secret',
            'app_id' => 'notifications',
            'options' => ['host' => 'localhost', 'port' => 8085, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => [],
        ],
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    // Channels live on the driver instance: register ours again on the Reverb driver.
    Broadcast::channel(UserChannel::NAME, UserChannel::class);
    $user = User::factory()->withMembership(null, OrgRole::Owner)->create();
    $other = User::factory()->withMembership(null, OrgRole::Owner)->create();
    Sanctum::actingAs($user);

    $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-user.'.$user->public_id])
        ->assertOk()
        ->assertJsonStructure(['auth']);
    $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-user.'.strtoupper($user->public_id)])
        ->assertOk();
    $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-user.'.$other->public_id])
        ->assertForbidden();
});

it('serves the mail logo publicly', function () {
    $response = $this->get('/mail/brand/bafo-mark.png')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('public');
});

it('registers the mail preview in the local environment only', function () {
    $this->get('/dev/mail')->assertNotFound();

    $controller = new MailPreviewController;
    $arabic = $controller->show(Request::create('/dev/mail/award.won', 'GET', ['locale' => 'ar']), 'award.won');
    $english = $controller->show(Request::create('/dev/mail/award.won', 'GET', ['locale' => 'en']), 'award.won');

    expect($arabic->getStatusCode())->toBe(200)
        ->and($arabic->getContent())->toContain('dir="rtl"')->toContain('تمت ترسية')->toContain('رسالة طارح المنافسة:')
        ->and($english->getContent())->toContain('dir="ltr"')->toContain('has been awarded to you')
        ->and($controller->index(Request::create('/dev/mail'))->render())->toContain('/dev/mail/award.won?locale=en')->not->toContain('offer.received');

    expect(fn () => $controller->show(Request::create('/dev/mail/offer.received'), 'offer.received'))->toThrow(NotFoundHttpException::class);
});

it('prunes devices not seen for 90 days and those of deleted users, weekly', function () {
    $fresh = DeviceToken::factory()->create();
    $stale = DeviceToken::factory()->create(['last_seen_at' => now()->subDays(91)]);
    $orphan = DeviceToken::factory()->create();
    $orphan->user?->delete();
    travel(1)->minutes();

    artisan('notifications:prune-devices')->expectsOutputToContain('Removed 2 device token(s).')->assertSuccessful();

    expect(DeviceToken::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and($stale->id)->not->toBe($fresh->id);

    artisan('schedule:list')->expectsOutputToContain('notifications:prune-devices');
});
