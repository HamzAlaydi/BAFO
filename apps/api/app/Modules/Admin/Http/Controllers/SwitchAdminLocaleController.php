<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Support\AdminLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `GET /admin/locale/{locale}`: the Arabic / English toggle of the panel's user menu (§16).
 * Stores the choice in the session and returns to the page the admin came from.
 */
final class SwitchAdminLocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        AdminLocale::set($locale);

        $home = url('/admin');
        $previous = url()->previous();

        return redirect()->to(str_starts_with($previous, url('/admin')) ? $previous : $home);
    }
}
