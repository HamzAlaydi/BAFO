<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\Web\FakeCheckoutController;
use App\Modules\Billing\Services\Gateways\FakePaymentGateway;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing module: web routes (middleware "web", loaded by BillingServiceProvider)
|--------------------------------------------------------------------------
|
| The fake gateway's hosted checkout page (ARCHITECTURE §13.6). The routes exist only when the
| gateway driver is `fake` and the app is not in production.
|
*/

if (config('bafo.billing.gateway.driver') === FakePaymentGateway::NAME && ! app()->isProduction()) {
    Route::prefix('pay/fake/{payment}')->name('billing.fake-pay.')->group(static function (): void {
        Route::get('/', [FakeCheckoutController::class, 'show'])->name('show');
        Route::post('approve', [FakeCheckoutController::class, 'approve'])->name('approve');
        Route::post('decline', [FakeCheckoutController::class, 'decline'])->name('decline');
    });
}
