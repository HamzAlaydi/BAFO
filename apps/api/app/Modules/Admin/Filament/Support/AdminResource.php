<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Policies\PanelResourcePolicy;
use App\Modules\Admin\Support\AdminScope;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Base of the panel resources (ARCHITECTURE §16).
 *
 * Authorization goes through PanelResourcePolicy, never through the models' module policies
 * (those are for organization users). Resources are read-only unless they list writable
 * abilities; the operator-restricted ones set `$superAdminOnly` (§8.7). Labels come from
 * `admin.resources.<langKey>.{label, plural_label}`.
 *
 * Release scope (RELEASE_SCOPE.md §11): each resource names its `$opsSurface`. When
 * AdminScope::visible() says no (scope `core`, a surface outside the core panel), canAccess() is
 * false, so the resource leaves the navigation and global search and its pages answer 403.
 */
abstract class AdminResource extends Resource
{
    protected static string $langKey = '';

    /** Operators may not open this resource (§8.7: admins, plans, prices, coupons, settings). */
    protected static bool $superAdminOnly = false;

    /**
     * Abilities allowed besides viewing, e.g. ['create', 'update', 'delete']. Empty: read-only.
     *
     * @var list<string>
     */
    protected static array $writableAbilities = [];

    /** The ops-panel surface this resource is (RELEASE_SCOPE.md §11); null: never hidden. */
    protected static ?OpsSurface $opsSurface = null;

    public static function opsSurface(): ?OpsSurface
    {
        return static::$opsSurface;
    }

    /**
     * Navigation, global search and every page of the resource: hidden by the release scope
     * first, then the panel policy (viewAny).
     */
    public static function canAccess(): bool
    {
        $surface = static::opsSurface();

        return ($surface === null || AdminScope::visible($surface)) && parent::canAccess();
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        return app(PanelResourcePolicy::class)->inspect(
            Auth::guard('admin')->user(),
            $ability,
            static::$superAdminOnly,
            static::$writableAbilities,
        );
    }

    /**
     * Eager load of an organization relation that keeps soft-deleted organizations: account
     * deletion keeps their payments, invoices, competitions and audit history.
     *
     * @return array<string, Closure(Relation<*, *, *>): mixed>
     */
    public static function withOrganization(string $relation = 'organization'): array
    {
        return [$relation => static fn (Relation $query): mixed => $query->withoutGlobalScope(SoftDeletingScope::class)];
    }

    public static function getModelLabel(): string
    {
        return Lang::get('resources.'.static::$langKey.'.label');
    }

    public static function getPluralModelLabel(): string
    {
        return Lang::get('resources.'.static::$langKey.'.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * A field label of this resource: `admin.resources.<langKey>.fields.<field>`, falling back
     * to the shared `admin.fields.<field>`.
     */
    public static function field(string $field): string
    {
        $key = 'admin.resources.'.static::$langKey.'.fields.'.$field;

        return __($key) !== $key ? Lang::get('resources.'.static::$langKey.'.fields.'.$field) : Lang::get('fields.'.$field);
    }
}
