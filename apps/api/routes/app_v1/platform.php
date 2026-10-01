<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\AppV1\AppConfigController;
use App\Modules\Platform\Http\Controllers\AppV1\ContactMessageController;
use App\Modules\Platform\Http\Controllers\AppV1\DownloadFileController;
use App\Modules\Platform\Http\Controllers\AppV1\HealthController;
use App\Modules\Platform\Http\Controllers\AppV1\LegalDocumentController;
use App\Modules\Platform\Http\Controllers\AppV1\TimeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform module: first-party API (/api/app/v1), API.md §1.1
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, request id, Accept-Language, version and maintenance gates, auth:sanctum,
| actor, throttle) and route names "app.v1.*".
|
*/

Route::withoutMiddleware('auth:sanctum')->group(static function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('app-config', AppConfigController::class)->name('app-config');
    Route::get('time', TimeController::class)->name('time');

    Route::post('contact', [ContactMessageController::class, 'store'])
        ->middleware('throttle:guest-forms')
        ->name('contact.store');

    Route::get('legal/{code}', [LegalDocumentController::class, 'show'])->name('legal.show');
});

Route::get('files/{file}/download', DownloadFileController::class)->name('files.download');
