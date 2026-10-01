<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\AppV1\LookupController;
use App\Modules\Catalog\Queries\Lookups;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog module: first-party API (/api/app/v1), API.md §1.2
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, Accept-Language, auth:sanctum, throttle) and route names "app.v1.*".
| The lookups are guest endpoints.
|
*/

Route::withoutMiddleware('auth:sanctum')->group(static function (): void {
    Route::get('lookups', [LookupController::class, 'index'])->name('lookups.index');

    Route::get('lookups/{type}', [LookupController::class, 'show'])
        ->whereIn('type', Lookups::APP_TYPES)
        ->name('lookups.show');
});
