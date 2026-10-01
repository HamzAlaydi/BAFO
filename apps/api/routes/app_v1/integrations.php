<?php

declare(strict_types=1);

use App\Modules\Integrations\Http\Controllers\AppV1\ApiClientController;
use App\Modules\Integrations\Http\Controllers\AppV1\ExportController;
use App\Modules\Integrations\Http\Controllers\AppV1\ImportController;
use App\Modules\Integrations\Http\Controllers\AppV1\VendorController;
use App\Modules\Integrations\Http\Controllers\AppV1\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Integrations module: first-party API (/api/app/v1), API.md §1.9
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1" and route names
| "app.v1.*". Permissions (API.md §1.9):
|
|   vendors                             `competitions.create` (VendorPolicy)
|   imports, exports                    `integrations.access`      (integrations.manage)
|   api clients, keys, webhooks         `integrations.access:api`  (integrations.manage + api_enabled)
|
*/

Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
Route::patch('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');

Route::prefix('integrations')->name('integrations.')->group(static function (): void {
    Route::middleware('integrations.access:api')->group(static function (): void {
        Route::get('api-clients', [ApiClientController::class, 'index'])->name('api-clients.index');
        Route::post('api-clients', [ApiClientController::class, 'store'])->name('api-clients.store');
        Route::get('api-clients/{client}', [ApiClientController::class, 'show'])->name('api-clients.show');
        Route::patch('api-clients/{client}', [ApiClientController::class, 'update'])->name('api-clients.update');
        Route::delete('api-clients/{client}', [ApiClientController::class, 'destroy'])->name('api-clients.destroy');
        Route::post('api-clients/{client}/rotate-secret', [ApiClientController::class, 'rotateSecret'])->name('api-clients.rotate-secret');
        Route::post('api-clients/{client}/keys', [ApiClientController::class, 'storeKey'])->name('api-clients.keys.store');
        Route::delete('api-clients/{client}/keys/{key}', [ApiClientController::class, 'destroyKey'])->name('api-clients.keys.destroy');

        Route::get('webhook-event-types', [WebhookEndpointController::class, 'eventTypes'])->name('webhook-event-types');
        Route::get('webhook-endpoints', [WebhookEndpointController::class, 'index'])->name('webhook-endpoints.index');
        Route::post('webhook-endpoints', [WebhookEndpointController::class, 'store'])->name('webhook-endpoints.store');
        Route::get('webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'show'])->name('webhook-endpoints.show');
        Route::patch('webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'update'])->name('webhook-endpoints.update');
        Route::delete('webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
        Route::post('webhook-endpoints/{endpoint}/test', [WebhookEndpointController::class, 'test'])->name('webhook-endpoints.test');
        Route::post('webhook-endpoints/{endpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhook-endpoints.rotate-secret');
        Route::get('webhook-endpoints/{endpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhook-endpoints.deliveries');
        Route::post('webhook-deliveries/{delivery}/redeliver', [WebhookEndpointController::class, 'redeliver'])->name('webhook-deliveries.redeliver');
    });

    Route::middleware('integrations.access')->group(static function (): void {
        Route::get('imports/templates/{type}', [ImportController::class, 'template'])->name('imports.template');
        Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{job}', [ImportController::class, 'show'])->name('imports.show');

        Route::post('exports', [ExportController::class, 'store'])->name('exports.store');
        Route::get('exports/{job}', [ExportController::class, 'show'])->name('exports.show');
    });
});
