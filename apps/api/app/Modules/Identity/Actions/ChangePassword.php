<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuthTokens;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * `PUT /me/password`: checks the current password (422 `password_incorrect`), sets the new one and
 * revokes every **other** token of the user.
 */
final readonly class ChangePassword
{
    public function __construct(private AuthTokens $tokens) {}

    public function handle(User $user, string $currentPassword, string $newPassword, ?string $bearerToken, Actor $actor): void
    {
        if ($user->password === null || ! Hash::check($currentPassword, $user->password)) {
            throw IdentityError::make('password_incorrect');
        }

        DB::transaction(function () use ($user, $newPassword, $bearerToken, $actor): void {
            $user->forceFill(['password' => $newPassword])->save();
            $this->tokens->revokeAll($user, $this->tokens->findForUser($user, $bearerToken)?->id);

            AuditLogger::log('user.password_changed', $user, actor: $actor);
        });
    }
}
