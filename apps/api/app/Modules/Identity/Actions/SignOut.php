<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuthTokens;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `POST /auth/logout`: deletes the token that authenticated the request.
 */
final readonly class SignOut
{
    public function __construct(private AuthTokens $tokens) {}

    public function handle(User $user, ?string $bearerToken, Actor $actor): void
    {
        DB::transaction(function () use ($user, $bearerToken, $actor): void {
            $this->tokens->findForUser($user, $bearerToken)?->delete();

            AuditLogger::log('user.signed_out', $user, actor: $actor);
        });
    }
}
