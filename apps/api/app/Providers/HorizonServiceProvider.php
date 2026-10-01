<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon dashboard at /horizon. Open in the local environment; elsewhere only
 * users allowed by the `viewHorizon` gate (platform admins, wired by Identity/Admin).
 */
final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn (?object $user = null): bool => $user !== null
            && method_exists($user, 'canAccessPanel')
            && (bool) $user->canAccessPanel(filament()->getPanel('admin')));
    }
}
