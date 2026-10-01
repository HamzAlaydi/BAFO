<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Admin\Policies\PanelResourcePolicy;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Base of the panel's relation managers: read-only lists shown on a record's view page, with
 * explicit module Actions where §16 lists them. Authorization goes through PanelResourcePolicy,
 * never through the related models' module policies. Title: `admin.relations.<langKey>`.
 */
abstract class AdminRelationManager extends RelationManager
{
    protected static string $langKey = '';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
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
