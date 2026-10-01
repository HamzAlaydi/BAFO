<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /me`: the user's name, phone and locale (mail and push language).
 */
final class UpdateProfile
{
    /**
     * @param  array{name?: string, phone?: string|null, locale?: string}  $attributes
     */
    public function handle(User $user, array $attributes, Actor $actor): User
    {
        return DB::transaction(static function () use ($user, $attributes, $actor): User {
            $user->fill($attributes)->save();

            if ($user->wasChanged()) {
                AuditLogger::log('user.updated', $user, AuditLogger::diff($user), actor: $actor);
            }

            return $user;
        });
    }
}
