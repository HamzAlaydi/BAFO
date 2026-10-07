<?php

declare(strict_types=1);

use App\Modules\Competitions\Http\Controllers\AppV1\CompetitionAttachmentController;
use App\Modules\Competitions\Http\Controllers\AppV1\CompetitionCommentController;
use App\Modules\Competitions\Http\Controllers\AppV1\CompetitionController;
use App\Modules\Competitions\Http\Controllers\AppV1\CompetitionInvitationController;
use App\Modules\Competitions\Http\Controllers\AppV1\CompetitionLifecycleController;
use App\Modules\Competitions\Http\Controllers\AppV1\CompetitionSuggestionController;
use App\Modules\Competitions\Http\Controllers\AppV1\HomeController;
use App\Modules\Competitions\Http\Controllers\AppV1\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Competitions module: first-party API (/api/app/v1)
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, Accept-Language, auth:sanctum, throttle) and route names "app.v1.*".
| Guest endpoints: Route::withoutMiddleware('auth:sanctum')->group(...).
| API.md §1.4 (issuer and shared) and §1.5 (invitee side).
|
*/

Route::get('home', HomeController::class)->name('home');

Route::prefix('competitions')->name('competitions.')->group(static function (): void {
    Route::get('/', [CompetitionController::class, 'index'])->name('index');
    Route::post('/', [CompetitionController::class, 'store'])->name('store');
    Route::get('{competition}', [CompetitionController::class, 'show'])->name('show');
    Route::patch('{competition}', [CompetitionController::class, 'update'])->name('update');
    Route::delete('{competition}', [CompetitionController::class, 'destroy'])->name('destroy');

    // Release scope (RELEASE_SCOPE.md §1.5): `feature:` gates. Extension is hidden in `core`; cancel,
    // attachments and comments are core in both scopes but keep the gate so a later release can flip them.
    Route::post('{competition}/publish', [CompetitionLifecycleController::class, 'publish'])->name('publish');
    Route::post('{competition}/extend', [CompetitionLifecycleController::class, 'extend'])->middleware('feature:extend_competition')->name('extend');
    Route::post('{competition}/cancel', [CompetitionLifecycleController::class, 'cancel'])->middleware('feature:cancel_competition')->name('cancel');
    Route::post('{competition}/close', [CompetitionLifecycleController::class, 'close'])->name('close');

    Route::get('{competition}/suggestions', CompetitionSuggestionController::class)->name('suggestions');

    Route::scopeBindings()->group(static function (): void {
        Route::get('{competition}/invitations', [CompetitionInvitationController::class, 'index'])->name('invitations.index');
        Route::post('{competition}/invitations', [CompetitionInvitationController::class, 'store'])->name('invitations.store');
        Route::patch('{competition}/invitations/{invitation}', [CompetitionInvitationController::class, 'update'])->name('invitations.update');
        Route::delete('{competition}/invitations/{invitation}', [CompetitionInvitationController::class, 'destroy'])->name('invitations.destroy');
        Route::post('{competition}/invitations/{invitation}/resend', [CompetitionInvitationController::class, 'resend'])->name('invitations.resend');

        Route::get('{competition}/attachments', [CompetitionAttachmentController::class, 'index'])->middleware('feature:attachments')->name('attachments.index');
        Route::post('{competition}/attachments', [CompetitionAttachmentController::class, 'store'])->middleware('feature:attachments')->name('attachments.store');
        Route::patch('{competition}/attachments/{attachment}', [CompetitionAttachmentController::class, 'update'])->middleware('feature:attachments')->name('attachments.update');
        Route::delete('{competition}/attachments/{attachment}', [CompetitionAttachmentController::class, 'destroy'])->middleware('feature:attachments')->name('attachments.destroy');
    });

    Route::get('{competition}/comments', [CompetitionCommentController::class, 'index'])->middleware('feature:qa_comments')->name('comments.index');
    Route::post('{competition}/comments', [CompetitionCommentController::class, 'store'])->middleware('feature:qa_comments')->name('comments.store');
});

Route::prefix('invitations')->name('invitations.')->group(static function (): void {
    Route::withoutMiddleware('auth:sanctum')->group(static function (): void {
        Route::post('lookup', [InvitationController::class, 'lookup'])->middleware('throttle:auth')->name('lookup');
        Route::post('decline', [InvitationController::class, 'declineByToken'])->middleware('throttle:guest-actions')->name('decline-by-token');
    });

    Route::post('claim', [InvitationController::class, 'claim'])->middleware('throttle:auth')->name('claim');
    Route::post('{invitation}/join', [InvitationController::class, 'join'])->name('join');
    Route::post('{invitation}/decline', [InvitationController::class, 'decline'])->name('decline');
});
