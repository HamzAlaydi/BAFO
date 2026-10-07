<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Policies\PanelResourcePolicy;
use App\Modules\Admin\Support\AdminScope;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Base of the panel's relation managers: read-only lists shown on a record's view page, with
 * explicit module Actions where §16 lists them. Authorization goes through PanelResourcePolicy,
 * never through the related models' module policies. Title: `admin.relations.<langKey>`.
 * A relation manager with an `$opsSurface` is hidden when AdminScope::visible() says no
 * (release scope `core`, RELEASE_SCOPE.md §11).
 */
abstract class AdminRelationManager extends RelationManager
{
    protected static string $langKey = '';

    /** The ops-panel surface this relation manager is (RELEASE_SCOPE.md §11); null: never hidden. */
    protected static ?OpsSurface $opsSurface = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (static::$opsSurface !== null && ! AdminScope::visible(static::$opsSurface)) {
            return false;
        }

        return app(PanelResourcePolicy::class)->inspect(Auth::guard('admin')->user(), 'viewAny', false, [])->allowed();
    }

    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return app(PanelResourcePolicy::class)->inspect(Auth::guard('admin')->user(), $action, false, []);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return Lang::get('relations.'.static::$langKey);
    }
}
