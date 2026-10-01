<?php

declare(strict_types=1);

use App\Modules\Integrations\Http\Controllers\Web\ApiDocsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Integrations module: web routes (middleware "web"), ARCHITECTURE §14.9
|--------------------------------------------------------------------------
*/

Route::get('docs/api', ApiDocsController::class)->name('integrations.docs');
