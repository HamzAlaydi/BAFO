<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;
use App\Support\Auth\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in platform admin (guard `admin`) and the Actor every module Action receives from
 * the panel (ARCHITECTURE §4.1, §16: `Actor::forAdmin()`).
 */
final class AdminActor
{
    public const string GUARD = 'admin';

    public static function admin(): Admin
    {
        $admin = Auth::guard(self::GUARD)->user();

        if (! $admin instanceof Admin || ! $admin->is_active) {
            throw new AuthorizationException;
        }

        return $admin;
    }

    public static function current(): Actor
    {
        return Actor::forAdmin(self::admin(), request());
    }

    public static function isSuperAdmin(): bool
    {
        $admin = Auth::guard(self::GUARD)->user();

        return $admin instanceof Admin && $admin->is_active && $admin->isSuperAdmin();
    }
}
