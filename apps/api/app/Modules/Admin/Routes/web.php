<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\SwitchAdminLocaleController;
use Illuminate\Support\Facades\Route;

/*
| Admin module web routes (loaded by the module provider with the `web` middleware).
| The Filament panel itself registers its routes in App\Providers\Filament\AdminPanelProvider.
*/

Route::get('admin/locale/{locale}', SwitchAdminLocaleController::class)
    ->whereIn('locale', ['ar', 'en'])
    ->name('admin.locale');
