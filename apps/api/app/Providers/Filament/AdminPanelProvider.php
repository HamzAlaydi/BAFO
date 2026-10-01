<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Admin\Filament\Auth\EditAdminProfile;
use App\Modules\Admin\Filament\Pages\Dashboard;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Http\Middleware\ApplyAdminLocale;
use App\Modules\Admin\Http\Middleware\BindAdminActor;
use App\Modules\Admin\Support\AdminLocale;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The platform admin panel (Filament 5) at /admin, owned by the Admin module (ARCHITECTURE §16).
 *
 * - Guard `admin` (provider `admins`, both added by AdminServiceProvider), session on Redis.
 * - App authentication (TOTP with recovery codes); required when `bafo.admin.mfa_required` (§8.7).
 * - Arabic (RTL) by default, English from the user menu; times shown in Asia/Riyadh. The
 *   navigation groups are the AdminNavigationGroup enum cases (in case order), labelled at render
 *   time so they follow the admin's language.
 * - Resources, pages and widgets live in app/Modules/Admin/Filament/{Resources,Pages,Widgets}
 *   and are discovered automatically. Every state change calls the owning module's Action.
 * - Colours follow assets/brand/04_ui_color_tokens (primary #0B7A55).
 */
final class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentTimezone::set(Display::TIMEZONE);
    }

    public function panel(Panel $panel): Panel
    {
        $filamentPath = app_path('Modules/Admin/Filament');
        $filamentNamespace = 'App\\Modules\\Admin\\Filament';

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->login()
            ->profile(EditAdminProfile::class, isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: static fn (): bool => (bool) config('bafo.admin.mfa_required'),
            )
            ->brandName('BAFO')
            ->font('IBM Plex Sans Arabic')
            ->colors([
                'primary' => Color::hex('#0B7A55'),
                'danger' => Color::hex('#B3261E'),
                'warning' => Color::hex('#B7791F'),
                'info' => Color::hex('#2563EB'),
                'success' => Color::hex('#0B7A55'),
                'gray' => Color::Slate,
            ])
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->discoverResources(in: $filamentPath.'/Resources', for: $filamentNamespace.'\\Resources')
            ->discoverPages(in: $filamentPath.'/Pages', for: $filamentNamespace.'\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: $filamentPath.'/Widgets', for: $filamentNamespace.'\\Widgets')
            ->userMenuItems([
                'locale' => Action::make('switchLocale')
                    ->label(static fn (): string => Lang::get('locale.switch'))
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(static fn (): string => route('admin.locale', ['locale' => AdminLocale::other()])),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([ApplyAdminLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authMiddleware([BindAdminActor::class], isPersistent: true);
    }
}
