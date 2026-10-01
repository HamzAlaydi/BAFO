<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Broadcasting\UserChannel;
use App\Modules\Notifications\Channels\PushChannel;
use App\Modules\Notifications\Console\Commands\PruneDevicesCommand;
use App\Modules\Notifications\Contracts\PushNotifier;
use App\Modules\Notifications\Http\Controllers\Web\MailAssetController;
use App\Modules\Notifications\Http\Controllers\Web\MailPreviewController;
use App\Modules\Notifications\Listeners\BroadcastNewNotification;
use App\Modules\Notifications\Listeners\NotifyAttachmentAdded;
use App\Modules\Notifications\Listeners\NotifyAwardIssued;
use App\Modules\Notifications\Listeners\NotifyAwardRevoked;
use App\Modules\Notifications\Listeners\NotifyBafoRoundEnded;
use App\Modules\Notifications\Listeners\NotifyBafoRoundStarted;
use App\Modules\Notifications\Listeners\NotifyCommentPosted;
use App\Modules\Notifications\Listeners\NotifyCompetitionCancelled;
use App\Modules\Notifications\Listeners\NotifyCompetitionClosed;
use App\Modules\Notifications\Listeners\NotifyCompetitionClosingSoon;
use App\Modules\Notifications\Listeners\NotifyCompetitionExtended;
use App\Modules\Notifications\Listeners\NotifyCompetitionFinalWindowStarted;
use App\Modules\Notifications\Listeners\NotifyCompetitionNotAwarded;
use App\Modules\Notifications\Listeners\NotifyCompetitionOpened;
use App\Modules\Notifications\Listeners\NotifyCompetitionUpdated;
use App\Modules\Notifications\Listeners\NotifyExportFinished;
use App\Modules\Notifications\Listeners\NotifyImportFinished;
use App\Modules\Notifications\Listeners\NotifyInvitationDeclined;
use App\Modules\Notifications\Listeners\NotifyInvitationJoined;
use App\Modules\Notifications\Listeners\NotifyInvitationSent;
use App\Modules\Notifications\Listeners\NotifyInvoiceIssued;
use App\Modules\Notifications\Listeners\NotifyOfferAccepted;
use App\Modules\Notifications\Listeners\NotifyOfferVoided;
use App\Modules\Notifications\Listeners\NotifyPaymentFailed;
use App\Modules\Notifications\Listeners\NotifySponsorshipPublishFailed;
use App\Modules\Notifications\Listeners\NotifySponsorshipSettled;
use App\Modules\Notifications\Listeners\NotifySubscriptionActivated;
use App\Modules\Notifications\Listeners\NotifySubscriptionExpired;
use App\Modules\Notifications\Listeners\NotifySubscriptionExpiring;
use App\Modules\Notifications\Listeners\NotifyVoucherIssued;
use App\Modules\Notifications\Listeners\NotifyWebhookEndpointDisabled;
use App\Modules\Notifications\Listeners\RemoveDeviceTokens;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Policies\DeviceTokenPolicy;
use App\Modules\Notifications\Policies\NotificationPolicy;
use App\Modules\Notifications\Services\LogPushNotifier;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Log\LogManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Notifications module.
 *
 * The notification catalogue (ARCHITECTURE §11), in-app notifications and the unread counter
 * (`user.{id}` channel), device tokens, push through PushNotifier (`log`), and the shared mail
 * theme (`resources/views/vendor/mail`).
 *
 * Contract bound here (§15.1): PushNotifier. Channel added: `push`. Broadcast channel: `user.{id}`.
 * HTTP routes: routes/app_v1/notifications.php. Web routes: the mail logo, and in `local` the
 * mail preview at /dev/mail.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class NotificationsServiceProvider extends ModuleServiceProvider
{
    /**
     * Domain events (§10) → the Notifications listeners. Bound by class-string (§3.4): an event
     * class that does not exist yet simply never fires.
     *
     * @var array<string, class-string>
     */
    public const array EVENT_LISTENERS = [
        'App\Modules\Identity\Events\AccountDeleted' => RemoveDeviceTokens::class,
        'App\Modules\Competitions\Events\InvitationSent' => NotifyInvitationSent::class,
        'App\Modules\Competitions\Events\CompetitionUpdated' => NotifyCompetitionUpdated::class,
        'App\Modules\Competitions\Events\AttachmentAdded' => NotifyAttachmentAdded::class,
        'App\Modules\Competitions\Events\CompetitionOpened' => NotifyCompetitionOpened::class,
        'App\Modules\Competitions\Events\CompetitionFinalWindowStarted' => NotifyCompetitionFinalWindowStarted::class,
        'App\Modules\Competitions\Events\CompetitionClosingSoon' => NotifyCompetitionClosingSoon::class,
        'App\Modules\Competitions\Events\CompetitionExtended' => NotifyCompetitionExtended::class,
        'App\Modules\Competitions\Events\CompetitionClosed' => NotifyCompetitionClosed::class,
        'App\Modules\Competitions\Events\CompetitionCancelled' => NotifyCompetitionCancelled::class,
        'App\Modules\Competitions\Events\CompetitionClosedWithoutAward' => NotifyCompetitionNotAwarded::class,
        'App\Modules\Competitions\Events\CommentPosted' => NotifyCommentPosted::class,
        'App\Modules\Competitions\Events\InvitationJoined' => NotifyInvitationJoined::class,
        'App\Modules\Competitions\Events\InvitationDeclined' => NotifyInvitationDeclined::class,
        'App\Modules\Bidding\Events\OfferAccepted' => NotifyOfferAccepted::class,
        'App\Modules\Bidding\Events\BafoRoundStarted' => NotifyBafoRoundStarted::class,
        'App\Modules\Bidding\Events\BafoRoundEnded' => NotifyBafoRoundEnded::class,
        'App\Modules\Bidding\Events\AwardIssued' => NotifyAwardIssued::class,
        'App\Modules\Bidding\Events\AwardRevoked' => NotifyAwardRevoked::class,
        'App\Modules\Bidding\Events\OfferVoided' => NotifyOfferVoided::class,
        'App\Modules\Billing\Events\PaymentFailed' => NotifyPaymentFailed::class,
        'App\Modules\Billing\Events\SubscriptionActivated' => NotifySubscriptionActivated::class,
        'App\Modules\Billing\Events\SubscriptionExpiring' => NotifySubscriptionExpiring::class,
        'App\Modules\Billing\Events\SubscriptionExpired' => NotifySubscriptionExpired::class,
        'App\Modules\Billing\Events\InvoiceIssued' => NotifyInvoiceIssued::class,
        'App\Modules\Billing\Events\SponsorshipSettled' => NotifySponsorshipSettled::class,
        'App\Modules\Billing\Events\SponsorshipPublishFailed' => NotifySponsorshipPublishFailed::class,
        'App\Modules\Billing\Events\VoucherIssued' => NotifyVoucherIssued::class,
        'App\Modules\Integrations\Events\WebhookEndpointDisabled' => NotifyWebhookEndpointDisabled::class,
        'App\Modules\Integrations\Events\ImportFinished' => NotifyImportFinished::class,
        'App\Modules\Integrations\Events\ExportFinished' => NotifyExportFinished::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        DatabaseNotification::class => NotificationPolicy::class,
        DeviceToken::class => DeviceTokenPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PushNotifier::class, static function (Application $app): PushNotifier {
            $driver = config('bafo.notifications.push.driver', 'log');
            $channel = config('bafo.notifications.push.log_channel', 'push');

            return match ($driver) {
                'log' => new LogPushNotifier($app->make(LogManager::class), is_string($channel) ? $channel : 'push'),
                default => throw new InvalidArgumentException('Unknown push driver ['.(is_string($driver) ? $driver : '?').'] (bafo.notifications.push.driver).'),
            };
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(ChannelManager::class, static function (ChannelManager $channels): void {
            $channels->extend('push', fn (Application $app): PushChannel => $app->make(PushChannel::class));
        });

        foreach (self::EVENT_LISTENERS as $event => $listener) {
            Event::listen($event, $listener);
        }

        Event::listen(NotificationSent::class, BroadcastNewNotification::class);

        // §9.2: the user's own channel only. Channel names carry public ids.
        Broadcast::channel(UserChannel::NAME, UserChannel::class);

        // `{notification}` is the row ULID (§5.8), any case; malformed ids are 404 before querying.
        Route::bind('notification', static function (string $value): DatabaseNotification {
            $notification = Str::isUlid($value) ? DatabaseNotification::query()->find(strtolower($value)) : null;

            return $notification instanceof DatabaseNotification
                ? $notification
                : throw (new ModelNotFoundException)->setModel(DatabaseNotification::class, [$value]);
        });

        $this->registerWebRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneDevicesCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('notifications:prune-devices')->weekly()->onOneServer();
        });
    }

    /**
     * The mail logo (public, no session) and, in `local`, the mail preview.
     */
    private function registerWebRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $logoPath = config('bafo.notifications.mail.logo_path', 'mail/brand/bafo-mark.png');

        Route::get(is_string($logoPath) ? $logoPath : 'mail/brand/bafo-mark.png', [MailAssetController::class, 'logo'])
            ->name('notifications.mail.logo');

        if ($this->app->environment('local')) {
            Route::middleware('web')->prefix('dev/mail')->name('notifications.mail-preview.')->group(static function (): void {
                Route::get('/', [MailPreviewController::class, 'index'])->name('index');
                Route::get('{type}', [MailPreviewController::class, 'show'])->name('show');
            });
        }
    }
}
