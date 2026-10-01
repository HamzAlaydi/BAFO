<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Modules\Admin\Models\Admin;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel auth middleware (persistent): makes the signed-in admin the current actor of the
 * request (ARCHITECTURE §4.1), so audit entries written during a panel request name the admin.
 * The panel still passes `Actor::forAdmin()` to every module Action explicitly.
 */
final class BindAdminActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin) {
            CurrentActor::set(Actor::forAdmin($admin, $request));
        }

        return $next($request);
    }
}
