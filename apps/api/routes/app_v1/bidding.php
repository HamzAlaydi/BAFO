<?php

declare(strict_types=1);

use App\Modules\Bidding\Http\Controllers\AppV1\AwardController;
use App\Modules\Bidding\Http\Controllers\AppV1\BafoRoundController;
use App\Modules\Bidding\Http\Controllers\AppV1\LiveController;
use App\Modules\Bidding\Http\Controllers\AppV1\OfferController;
use App\Modules\Bidding\Http\Controllers\AppV1\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bidding module: first-party API (/api/app/v1), API.md §1.6
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, request id, Accept-Language, version and maintenance gates, auth:sanctum,
| actor, throttle) and route names "app.v1.*".
|
*/

Route::prefix('competitions/{competition}')->name('competitions.')->group(static function (): void {
    Route::get('live', [LiveController::class, 'show'])->name('live');
    Route::post('live/heartbeat', [LiveController::class, 'heartbeat'])->name('live.heartbeat');

    // ARCHITECTURE §7.4: 60 per minute per user here; 1 per 2 s per participant in the engine.
    Route::post('offers', [OfferController::class, 'store'])->middleware('throttle:offers')->name('offers.store');
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('offers/log', [OfferController::class, 'log'])->name('offers.log');
    Route::get('my-offers', [OfferController::class, 'mine'])->name('my-offers');

    Route::post('bafo-round', [BafoRoundController::class, 'store'])->name('bafo-round.store');

    Route::post('award', [AwardController::class, 'store'])->name('award.store');
    Route::get('award', [AwardController::class, 'show'])->name('award.show');
    Route::post('award/revoke', [AwardController::class, 'revoke'])->name('award.revoke');

    Route::get('report', [ReportController::class, 'show'])->name('report');
});
