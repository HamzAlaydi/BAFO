<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Enums\OpsSurface;
use App\Support\Features\FeatureFlags;

/**
 * The single release-scope check of the ops panel (RELEASE_SCOPE.md §11).
 *
 *   AdminScope::visible(OpsSurface::Plans)   false in `core`, true in `full`
 *
 * A surface is visible when it belongs to the core panel (OpsSurface::inCore()) or when the scope
 * is `full`. Resources and pages use it in `canAccess()` (navigation and direct URLs), the pages
 * that stay in core use it on the actions, columns and relation managers they hide. Only the
 * panel layer reads it: the module Actions the panel calls are never gated.
 */
final class AdminScope
{
    public static function visible(OpsSurface $surface): bool
    {
        return $surface->inCore() || self::full();
    }

    /** Whether the release scope is `full` (every surface visible). */
    public static function full(): bool
    {
        return app(FeatureFlags::class)->scope()->isFull();
    }
}
