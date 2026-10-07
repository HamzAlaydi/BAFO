<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AppV1\AccountDeletionController;
use App\Modules\Identity\Http\Controllers\AppV1\MeController;
use App\Modules\Identity\Http\Controllers\AppV1\OrganizationController;
use App\Modules\Identity\Http\Controllers\AppV1\OtpController;
use App\Modules\Identity\Http\Controllers\AppV1\PasswordResetController;
use App\Modules\Identity\Http\Controllers\AppV1\RegisterController;
use App\Modules\Identity\Http\Controllers\AppV1\SessionController;
use App\Modules\Identity\Http\Controllers\AppV1\TeamInvitationController;
use App\Modules\Identity\Http\Controllers\AppV1\TeamMemberController;
use App\Modules\Identity\Models\Membership;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity module: first-party API (/api/app/v1), API.md §1.3
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "app_v1"
| (JSON, Accept-Language, auth:sanctum, actor, throttle, bindings, and Identity's
| EnsureAccountActive) and route names "app.v1.*".
| Guest endpoints: Route::withoutMiddleware('auth:sanctum')->group(...).
|
*/

// Authentication (guest, `auth` limiter: 10/min per IP and 5/min per e-mail).
Route::withoutMiddleware('auth:sanctum')
    ->middleware('throttle:auth')
    ->prefix('auth')
    ->name('auth.')
    ->group(static function (): void {
        Route::post('register', RegisterController::class)->name('register');

        Route::post('otp/send', [OtpController::class, 'send'])->name('otp.send');
        Route::post('otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
        Route::post('otp/check', [OtpController::class, 'check'])->name('otp.check');

        Route::post('login', [SessionController::class, 'store'])->name('login');

        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');

        Route::post('team-invitations/lookup', [TeamInvitationController::class, 'lookup'])->name('team-invitations.lookup');
        Route::post('team-invitations/accept', [TeamInvitationController::class, 'accept'])->name('team-invitations.accept');
    });

Route::post('auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');

// Current user.
Route::get('me', [MeController::class, 'show'])->name('me.show');
Route::patch('me', [MeController::class, 'update'])->name('me.update');
Route::put('me/password', [MeController::class, 'password'])->middleware('throttle:password-confirm')->name('me.password');
Route::post('me/avatar', [MeController::class, 'storeAvatar'])->name('me.avatar.store');
Route::delete('me/avatar', [MeController::class, 'destroyAvatar'])->name('me.avatar.destroy');

// Own organization.
Route::get('organization', [OrganizationController::class, 'show'])->name('organization.show');

Route::middleware("can:perm,'organization.update'")->group(static function (): void {
    Route::patch('organization', [OrganizationController::class, 'update'])->name('organization.update');
    Route::post('organization/logo', [OrganizationController::class, 'storeLogo'])->name('organization.logo.store');
    Route::delete('organization/logo', [OrganizationController::class, 'destroyLogo'])->name('organization.logo.destroy');
    Route::post('organization/profile-document', [OrganizationController::class, 'storeProfileDocument'])->name('organization.profile-document.store');
    Route::delete('organization/profile-document', [OrganizationController::class, 'destroyProfileDocument'])->name('organization.profile-document.destroy');
});

// Team (branch users): `team.manage`, own organization only (MembershipPolicy). Hidden while the
// release scope leaves `team_management` off (RELEASE_SCOPE.md §1.5); accepting an invitation stays.
Route::prefix('team/members')->middleware('feature:team_management')->name('team.members.')->group(static function (): void {
    Route::get('/', [TeamMemberController::class, 'index'])->middleware('can:viewAny,'.Membership::class)->name('index');
    Route::post('/', [TeamMemberController::class, 'store'])->middleware('can:create,'.Membership::class)->name('store');
    Route::patch('{membership}', [TeamMemberController::class, 'update'])->middleware('can:update,membership')->name('update');
    Route::delete('{membership}', [TeamMemberController::class, 'destroy'])->middleware('can:delete,membership')->name('destroy');
    Route::post('{membership}/resend-invitation', [TeamMemberController::class, 'resend'])->middleware('can:resendInvitation,membership')->name('resend');
});

// Account deletion (store requirement; exempt from the account gate).
Route::post('account/deletion', [AccountDeletionController::class, 'store'])->middleware('throttle:password-confirm')->name('account.deletion.store');
Route::get('account/deletion', [AccountDeletionController::class, 'show'])->name('account.deletion.show');
Route::delete('account/deletion', [AccountDeletionController::class, 'destroy'])->name('account.deletion.destroy');
