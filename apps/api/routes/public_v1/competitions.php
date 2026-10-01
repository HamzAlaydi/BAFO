<?php

declare(strict_types=1);

use App\Modules\Competitions\Http\Controllers\PublicV1\CompetitionAttachmentController;
use App\Modules\Competitions\Http\Controllers\PublicV1\CompetitionController;
use App\Modules\Competitions\Http\Controllers\PublicV1\CompetitionInvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Competitions module: public ERP API (/api/public/v1), API.md §3.4
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "public_v1"
| (JSON, Accept-Language; Integrations appends API client auth, throttle:public-api
| and bindings) and route names "public.v1.*". Every route declares its scope with
| ->middleware('api.scope:<scope>'), plus `idempotent` on POSTs that create or act.
|
*/

Route::prefix('competitions')->name('competitions.')->group(static function (): void {
    Route::get('/', [CompetitionController::class, 'index'])->middleware('api.scope:competitions:read')->name('index');
    Route::post('/', [CompetitionController::class, 'store'])->middleware(['api.scope:competitions:write', 'idempotent'])->name('store');
    Route::get('{competition}', [CompetitionController::class, 'show'])->middleware('api.scope:competitions:read')->name('show');
    Route::patch('{competition}', [CompetitionController::class, 'update'])->middleware('api.scope:competitions:write')->name('update');
    Route::delete('{competition}', [CompetitionController::class, 'destroy'])->middleware('api.scope:competitions:write')->name('destroy');

    Route::post('{competition}/publish', [CompetitionController::class, 'publish'])->middleware(['api.scope:competitions:publish', 'idempotent'])->name('publish');
    Route::post('{competition}/extend', [CompetitionController::class, 'extend'])->middleware(['api.scope:competitions:manage', 'idempotent'])->name('extend');
    Route::post('{competition}/cancel', [CompetitionController::class, 'cancel'])->middleware(['api.scope:competitions:manage', 'idempotent'])->name('cancel');
    Route::post('{competition}/close', [CompetitionController::class, 'close'])->middleware(['api.scope:competitions:manage', 'idempotent'])->name('close');

    Route::scopeBindings()->group(static function (): void {
        Route::get('{competition}/attachments', [CompetitionAttachmentController::class, 'index'])->middleware('api.scope:competitions:read')->name('attachments.index');
        Route::post('{competition}/attachments', [CompetitionAttachmentController::class, 'store'])->middleware(['api.scope:competitions:write', 'idempotent'])->name('attachments.store');
        Route::delete('{competition}/attachments/{attachment}', [CompetitionAttachmentController::class, 'destroy'])->middleware('api.scope:competitions:write')->name('attachments.destroy');

        Route::get('{competition}/invitations', [CompetitionInvitationController::class, 'index'])->middleware('api.scope:invitations:read')->name('invitations.index');
        Route::post('{competition}/invitations', [CompetitionInvitationController::class, 'store'])->middleware(['api.scope:invitations:write', 'idempotent'])->name('invitations.store');
        Route::delete('{competition}/invitations/{invitation}', [CompetitionInvitationController::class, 'destroy'])->middleware('api.scope:invitations:write')->name('invitations.destroy');
    });
});
