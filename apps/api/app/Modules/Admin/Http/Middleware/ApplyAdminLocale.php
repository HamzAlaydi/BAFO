<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Modules\Admin\Support\AdminLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel middleware (persistent, so Livewire requests get it too): applies the admin's panel
 * language, Arabic by default (ARCHITECTURE §16).
 */
final class ApplyAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(AdminLocale::current());

        return $next($request);
    }
}
