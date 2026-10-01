<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Console\Commands\ExpireSubscriptionsCommand;
use App\Modules\Billing\Console\Commands\ReconcilePaymentsCommand;
use App\Modules\Billing\Console\Commands\RetryEInvoicesCommand;
use App\Modules\Billing\Console\Commands\SubscriptionRemindersCommand;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Contracts\EInvoicing;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Contracts\SponsorshipService;
use App\Modules\Billing\Events\PaymentSucceeded;
use App\Modules\Billing\Listeners\IssueInvoiceForPayment;
use App\Modules\Billing\Listeners\ReleasePassForInvitation;
use App\Modules\Billing\Listeners\SettleSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\CouponPolicy;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Billing\Policies\PaymentPolicy;
use App\Modules\Billing\Policies\SponsorshipPolicy;
use App\Modules\Billing\Policies\SubscriptionPolicy;
use App\Modules\Billing\Services\DbAccessPolicy;
use App\Modules\Billing\Services\DbSponsorshipService;
use App\Modules\Billing\Services\EInvoicing\FakeEInvoicing;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Settings\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Billing module.
 *
 * Plans, subscriptions, trials, grants, coupons and vouchers; checkout through the
 * PaymentGateway interface (fake, moyasar) and its fake hosted page; tax invoices through
 * EInvoicing (fake); R4 sponsorship and sponsored passes; and AccessPolicy.
 *
 * HTTP routes: routes/app_v1/billing.php; web routes (fake checkout): Routes/web.php.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class BillingServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Payment::class => PaymentPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Coupon::class => CouponPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AccessPolicy::class, DbAccessPolicy::class);
        $this->app->singleton(SponsorshipService::class, DbSponsorshipService::class);
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->bind(PaymentGateway::class, static fn (Application $app): PaymentGateway => $app->make(PaymentGatewayManager::class)->driver());
        $this->app->singleton(EInvoicing::class, static function (Application $app): EInvoicing {
            $driver = config('bafo.billing.einvoicing.driver', FakeEInvoicing::NAME);

            return match ($driver) {
                FakeEInvoicing::NAME => $app->make(FakeEInvoicing::class),
                default => throw new InvalidArgumentException('Unknown e-invoicing driver ['.(is_string($driver) ? $driver : '?').'].'),
            };
        });
    }

    public function boot(): void
    {
        parent::boot();

        /** @var array<string, mixed> $defaults */
        $defaults = config('bafo.billing.settings', []);
        $this->app->make(Settings::class)->defaults($defaults);

        Gate::define(SponsorshipPolicy::VIEW, [SponsorshipPolicy::class, 'view']);
        Gate::define(SponsorshipPolicy::MANAGE, [SponsorshipPolicy::class, 'manage']);
        Gate::define(SponsorshipPolicy::CHECKOUT, [SponsorshipPolicy::class, 'checkout']);

        // Invoice PDFs: members of the buyer organization with `billing.view` (ARCHITECTURE §8.5).
        $this->app->make(FileAccessRegistry::class)->register(
            FilePurpose::InvoicePdf,
            static fn (Authenticatable $user, File $file): bool => $user instanceof User
                && $file->organization_id !== null
                && $user->membership?->organization_id === $file->organization_id
                && $user->hasPermission(Permission::BillingView),
        );

        Event::listen(PaymentSucceeded::class, IssueInvoiceForPayment::class);
        Event::listen(InvitationDeclined::class, [ReleasePassForInvitation::class, 'onDeclined']);
        Event::listen(InvitationRevoked::class, [ReleasePassForInvitation::class, 'onRevoked']);
        Event::listen(CompetitionClosed::class, SettleSponsorship::class);
        Event::listen(CompetitionCancelled::class, SettleSponsorship::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireSubscriptionsCommand::class,
                SubscriptionRemindersCommand::class,
                ReconcilePaymentsCommand::class,
                RetryEInvoicesCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('billing:expire-subscriptions')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('billing:subscription-reminders')->dailyAt('06:00')->onOneServer();
            $schedule->command('billing:reconcile-payments')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('billing:retry-einvoices')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
