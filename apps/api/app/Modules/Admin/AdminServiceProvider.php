<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Modules\Admin\Listeners\RecordAdminSignIn;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Policies\AdminPolicy;
use App\Modules\Admin\Support\AdminGate;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * Admin module.
 *
 * The platform admin panel (Filament) at /admin: admins and the `admin` session guard, resources,
 * pages and widgets in app/Modules/Admin/Filament/**. The panel itself is configured in
 * App\Providers\Filament\AdminPanelProvider. Every state change of another module goes through
 * that module's Actions with `Actor::forAdmin()` (ARCHITECTURE §16).
 */
final class AdminServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Admin::class => AdminPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        // §16: the guard and provider are added at runtime; config/auth.php stays Platform's.
        config([
            'auth.guards.admin' => ['driver' => 'session', 'provider' => 'admins'],
            'auth.providers.admins' => ['driver' => 'eloquent', 'model' => Admin::class],
        ]);

        Relation::morphMap(['admin' => Admin::class]);
    }

    public function boot(): void
    {
        parent::boot();

        // Module policies are written for organization users: an admin never reaches them.
        Gate::before(static fn (mixed $user, string $ability, array $arguments = []): ?bool => $user instanceof Admin
            ? AdminGate::before($user, $ability, $arguments)
            : null);

        Event::listen(Login::class, RecordAdminSignIn::class);
    }
}
