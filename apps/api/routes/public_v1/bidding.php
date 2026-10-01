<?php

declare(strict_types=1);

use App\Modules\Bidding\Http\Controllers\PublicV1\PublicAwardController;
use App\Modules\Bidding\Http\Controllers\PublicV1\PublicOfferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bidding module: public ERP API (/api/public/v1), API.md §3.5
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "public_v1"
| (JSON, Accept-Language; Integrations appends API client auth, throttle:public-api
| and bindings) and route names "public.v1.*". Every route declares its scope with
| ->middleware('api.scope:<scope>'), plus `idempotent` on POSTs that create or act.
|
| Route parameters are resolved by the controllers, scoped to the client's organization
| (ARCHITECTURE §8.6), so they are declared as plain strings.
|
*/

Route::middleware('api.scope:offers:read')->group(static function (): void {
    Route::get('competitions/{competition}/offers', [PublicOfferController::class, 'index'])->name('competitions.offers.index');
    Route::get('competitions/{competition}/results', [PublicOfferController::class, 'results'])->name('competitions.results');
});

Route::middleware('api.scope:awards:read')->group(static function (): void {
    Route::get('awards', [PublicAwardController::class, 'index'])->name('awards.index');
    Route::get('awards/{award}', [PublicAwardController::class, 'show'])->name('awards.show');
});

Route::post('awards/{award}/erp-sync', [PublicAwardController::class, 'erpSync'])
    ->middleware(['api.scope:awards:sync', 'idempotent'])
    ->name('awards.erp-sync');
