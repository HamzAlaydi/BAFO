<?php

declare(strict_types=1);

use App\Modules\Integrations\Http\Controllers\PublicV1\IssueAccessTokenController;
use App\Modules\Integrations\Http\Controllers\PublicV1\MetaController;
use App\Modules\Integrations\Http\Controllers\PublicV1\OpenApiController;
use App\Modules\Integrations\Http\Controllers\PublicV1\VendorController;
use App\Modules\Integrations\Http\Controllers\PublicV1\WebhookEndpointController;
use App\Modules\Integrations\Http\Middleware\AuthenticatePublicApiClient;
use App\Modules\Integrations\Http\Middleware\ShapeOAuthErrors;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Integrations module: public ERP API (/api/public/v1), API.md §3.1, §3.3, §3.6
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "public_v1" (JSON, request id,
| Accept-Language, then `api.client`, `throttle:public-api` and SubstituteBindings appended by
| IntegrationsServiceProvider) and route names "public.v1.*". Every route declares its scope with
| `api.scope:<scope>`; POSTs that create or act are `idempotent`.
|
*/

Route::withoutMiddleware([AuthenticatePublicApiClient::class, 'throttle:public-api'])->group(static function (): void {
    Route::post('oauth/token', IssueAccessTokenController::class)
        ->middleware([ShapeOAuthErrors::class, 'throttle:oauth-token'])
        ->name('oauth.token');

    Route::get('openapi.yaml', [OpenApiController::class, 'yaml'])->name('openapi');
    // CONTRACT-GAP: API.md §3.1 lists openapi.yaml; the JSON rendering feeds /docs/api and tooling.
    Route::get('openapi.json', [OpenApiController::class, 'json'])->name('openapi.json');
});

Route::get('ping', [MetaController::class, 'ping'])->name('ping');
Route::get('client', [MetaController::class, 'client'])->name('client');

Route::get('vendors', [VendorController::class, 'index'])->middleware('api.scope:vendors:read')->name('vendors.index');
Route::post('vendors', [VendorController::class, 'store'])->middleware(['api.scope:vendors:write', 'idempotent'])->name('vendors.store');
Route::put('vendors/external/{system}/{external_id}', [VendorController::class, 'upsert'])
    ->middleware('api.scope:vendors:write')->where('external_id', '.+')->name('vendors.upsert');
Route::get('vendors/external/{system}/{external_id}', [VendorController::class, 'showByExternal'])
    ->middleware('api.scope:vendors:read')->where('external_id', '.+')->name('vendors.by-external');
Route::get('vendors/{vendor}', [VendorController::class, 'show'])->middleware('api.scope:vendors:read')->name('vendors.show');
Route::patch('vendors/{vendor}', [VendorController::class, 'update'])->middleware('api.scope:vendors:write')->name('vendors.update');

Route::middleware('api.scope:webhooks:manage')->group(static function (): void {
    Route::get('webhook-event-types', [WebhookEndpointController::class, 'eventTypes'])->name('webhook-event-types');
    Route::get('webhook-endpoints', [WebhookEndpointController::class, 'index'])->name('webhook-endpoints.index');
    Route::post('webhook-endpoints', [WebhookEndpointController::class, 'store'])->middleware('idempotent')->name('webhook-endpoints.store');
    Route::get('webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'show'])->name('webhook-endpoints.show');
    Route::patch('webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'update'])->name('webhook-endpoints.update');
    Route::delete('webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
    Route::post('webhook-endpoints/{endpoint}/test', [WebhookEndpointController::class, 'test'])->middleware('idempotent')->name('webhook-endpoints.test');
    Route::post('webhook-endpoints/{endpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->middleware('idempotent')->name('webhook-endpoints.rotate-secret');
    Route::get('webhook-endpoints/{endpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhook-endpoints.deliveries');
    Route::post('webhook-deliveries/{delivery}/redeliver', [WebhookEndpointController::class, 'redeliver'])->middleware('idempotent')->name('webhook-deliveries.redeliver');
});
