<?php

declare(strict_types=1);

namespace App\Modules\Admin\Listeners;

use App\Modules\Admin\Models\Admin;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;

/**
 * Stamps `admins.last_login_at` and audits `admin.signed_in` when an admin signs in to the
 * panel. Synchronous: one small update in the sign-in request, no domain event involved.
 */
final class RecordAdminSignIn
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'admin' || ! $event->user instanceof Admin) {
            return;
        }

        $admin = $event->user;
        $admin->forceFill(['last_login_at' => Date::now()])->saveQuietly();

        AuditLogger::log('admin.signed_in', $admin, actor: Actor::forAdmin($admin, request()));
    }
}
