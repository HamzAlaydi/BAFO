<?php

declare(strict_types=1);

namespace Tests\Support\Admin;

use App\Modules\Admin\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

/**
 * Admin panel test helpers: super admins and operators on the `admin` guard, with the Filament
 * panel selected (for Livewire::test on panel pages), and the panel's URLs.
 */
final class AdminPanel
{
    /**
     * Every list page of §16, by URL.
     *
     * @var list<string>
     */
    public const array INDEXES = [
        '/admin',
        '/admin/organizations',
        '/admin/users',
        '/admin/account-deletions',
        '/admin/competitions',
        '/admin/subscriptions',
        '/admin/payments',
        '/admin/invoices',
        '/admin/sponsorships',
        '/admin/vouchers',
        '/admin/api-clients',
        '/admin/webhook-endpoints',
        '/admin/regions',
        '/admin/categories',
        '/admin/close-reasons',
        '/admin/presets',
        '/admin/legal-documents',
        '/admin/contact-messages',
        '/admin/audit-log',
    ];

    /**
     * Pages operators may not open (§8.7: admins, plans, prices, coupons, settings).
     *
     * @var list<string>
     */
    public const array SUPER_ADMIN_ONLY = [
        '/admin/plans',
        '/admin/plans/create',
        '/admin/coupons',
        '/admin/coupons/create',
        '/admin/settings',
        '/admin/admins',
        '/admin/admins/create',
    ];

    /**
     * The list pages of the minimal ops panel: shown in release scope `core` (RELEASE_SCOPE.md §11).
     *
     * @var list<string>
     */
    public const array CORE_PAGES = [
        '/admin',
        '/admin/organizations',
        '/admin/users',
        '/admin/competitions',
        '/admin/subscriptions',
        '/admin/settings',
    ];

    /**
     * Every other page without a record: hidden in `core` (403), back in `full`.
     *
     * @var list<string>
     */
    public const array FULL_ONLY_PAGES = [
        '/admin/account-deletions',
        '/admin/plans',
        '/admin/plans/create',
        '/admin/coupons',
        '/admin/coupons/create',
        '/admin/vouchers',
        '/admin/payments',
        '/admin/invoices',
        '/admin/sponsorships',
        '/admin/api-clients',
        '/admin/webhook-endpoints',
        '/admin/regions',
        '/admin/regions/create',
        '/admin/categories',
        '/admin/categories/create',
        '/admin/close-reasons',
        '/admin/close-reasons/create',
        '/admin/presets',
        '/admin/presets/create',
        '/admin/legal-documents',
        '/admin/legal-documents/create',
        '/admin/contact-messages',
        '/admin/audit-log',
        '/admin/admins',
        '/admin/admins/create',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function superAdmin(array $attributes = []): Admin
    {
        return Admin::factory()->superAdmin()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function operator(array $attributes = []): Admin
    {
        return Admin::factory()->create($attributes);
    }

    /**
     * Signs the admin in on the `admin` guard and selects the panel.
     */
    public static function signIn(?Admin $admin = null): Admin
    {
        $admin ??= self::superAdmin();

        Auth::forgetGuards();
        Auth::guard('admin')->setUser($admin);
        Auth::shouldUse('admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }
}
