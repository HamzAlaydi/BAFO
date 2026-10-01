<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Resources\Invoices\InvoiceResource;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Payments\PaymentResource;
use App\Modules\Admin\Filament\Resources\Sponsorships\SponsorshipResource;
use App\Modules\Admin\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Support\PlatformMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * §16 Dashboard counters: organizations, active subscriptions, live competitions, payments
 * today, failed e-invoices and sponsorships awaiting a voucher.
 */
final class PlatformStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $metrics = app(PlatformMetrics::class);
        [$paymentsToday, $collectedToday] = $metrics->paymentsToday();
        $failedEInvoices = $metrics->failedEInvoices();
        $awaitingVoucher = $metrics->sponsorshipsAwaitingVoucher();

        return [
            Stat::make(Lang::get('dashboard.stats.organizations'), (string) $metrics->organizations())
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->url(OrganizationResource::getUrl()),
            Stat::make(Lang::get('dashboard.stats.active_subscriptions'), (string) $metrics->activeSubscriptions())
                ->icon(Heroicon::OutlinedCheckBadge)
                ->url(SubscriptionResource::getUrl()),
            Stat::make(Lang::get('dashboard.stats.live_competitions'), (string) $metrics->liveCompetitions())
                ->icon(Heroicon::OutlinedSignal)
                ->url(CompetitionResource::getUrl()),
            Stat::make(Lang::get('dashboard.stats.payments_today'), (string) $paymentsToday)
                ->description(Display::money($collectedToday))
                ->icon(Heroicon::OutlinedBanknotes)
                ->url(PaymentResource::getUrl()),
            Stat::make(Lang::get('dashboard.stats.failed_einvoices'), (string) $failedEInvoices)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($failedEInvoices > 0 ? 'danger' : null)
                ->url(InvoiceResource::getUrl()),
            Stat::make(Lang::get('dashboard.stats.sponsorships_awaiting_voucher'), (string) $awaitingVoucher)
                ->icon(Heroicon::OutlinedGift)
                ->color($awaitingVoucher > 0 ? 'warning' : null)
                ->url(SponsorshipResource::getUrl()),
        ];
    }
}
