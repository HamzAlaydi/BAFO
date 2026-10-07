<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\AppV1\CouponController;
use App\Modules\Billing\Http\Controllers\AppV1\GatewayWebhookController;
use App\Modules\Billing\Http\Controllers\AppV1\InvoiceController;
use App\Modules\Billing\Http\Controllers\AppV1\PaymentController;
use App\Modules\Billing\Http\Controllers\AppV1\PlanController;
use App\Modules\Billing\Http\Controllers\AppV1\SponsorshipController;
use App\Modules\Billing\Http\Controllers\AppV1\SubscriptionCheckoutController;
use App\Modules\Billing\Http\Controllers\AppV1\SubscriptionController;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Policies\SponsorshipPolicy;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing module: first-party API (/api/app/v1), API.md §1.7
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, request id, Accept-Language, version and maintenance gates, auth:sanctum,
| actor, throttle) and route names "app.v1.*". Permission checks that must precede input
| validation (checkout, ARCHITECTURE §13.1) run as `can:` route middleware.
|
*/

// Release scope (RELEASE_SCOPE.md §1.5): the custom quote, coupon validation and the sponsorship
// checkout are `feature:`-gated; plans, vouchers, invoices and the sponsorship reads stay (existing records).
Route::withoutMiddleware('auth:sanctum')->group(static function (): void {
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/custom-quote', [PlanController::class, 'customQuote'])->middleware('feature:custom_plan_quote')->name('plans.custom-quote');

    Route::post('billing/gateway-webhooks/{gateway}', GatewayWebhookController::class)
        ->where('gateway', '[a-z0-9_]+')
        ->name('billing.gateway-webhooks');
});

Route::prefix('billing')->name('billing.')->group(static function (): void {
    Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription');
    Route::post('trial', [SubscriptionController::class, 'trial'])->name('trial');

    Route::post('coupons/validate', [CouponController::class, 'validateCode'])
        ->middleware('feature:coupons')
        ->can('validate', Coupon::class)
        ->name('coupons.validate');

    Route::post('checkout/subscription', SubscriptionCheckoutController::class)
        ->can('purchase', Payment::class)
        ->middleware('idempotent:optional')
        ->name('checkout.subscription');

    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    Route::get('vouchers', [CouponController::class, 'vouchers'])->name('vouchers.index');
});

Route::prefix('competitions/{competition}/sponsorship')->name('competitions.sponsorship.')->group(static function (): void {
    Route::get('/', [SponsorshipController::class, 'show'])
        ->middleware('can:'.SponsorshipPolicy::VIEW.',competition')
        ->name('show');
    Route::put('/', [SponsorshipController::class, 'update'])
        ->middleware('can:'.SponsorshipPolicy::MANAGE.',competition')
        ->name('update');
    Route::get('quote', [SponsorshipController::class, 'quote'])
        ->middleware('can:'.SponsorshipPolicy::VIEW.',competition')
        ->name('quote');
    Route::post('checkout', [SponsorshipController::class, 'checkout'])
        ->middleware(['feature:sponsorship', 'can:'.SponsorshipPolicy::CHECKOUT.',competition', 'idempotent:optional'])
        ->name('checkout');
});
