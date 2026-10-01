<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notifications module: first-party API (/api/app/v1)
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, Accept-Language, auth:sanctum, throttle) and route names "app.v1.*".
| API.md §1.8. `{notification}` binds the row ULID (NotificationsServiceProvider),
| `{device}` the device public id.
|
*/

use App\Modules\Notifications\Http\Controllers\AppV1\DeviceController;
use App\Modules\Notifications\Http\Controllers\AppV1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::controller(NotificationController::class)->prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('unread-count', 'unreadCount')->name('unread-count');
    Route::post('read-all', 'readAll')->name('read-all');
    Route::post('{notification}/read', 'read')->name('read');
    Route::delete('/', 'destroyAll')->name('destroy-all');
    Route::delete('{notification}', 'destroy')->name('destroy');
});

Route::controller(DeviceController::class)->prefix('devices')->name('devices.')->group(function (): void {
    Route::post('/', 'store')->name('store');
    Route::delete('{device}', 'destroy')->name('destroy');
});
