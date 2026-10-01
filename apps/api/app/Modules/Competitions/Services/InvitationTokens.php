<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Exceptions\ApiException;

/**
 * Finds an invitation by its one-time token (ARCHITECTURE §13.11). The token arrives in a JSON
 * body (never a URL, D11); only its sha256 is stored. Unknown, revoked and draft invitations are
 * 404 `invitation_invalid`.
 */
final class InvitationTokens
{
    public function find(string $token, bool $lock = false): Invitation
    {
        $token = trim($token);

        $query = Invitation::query()->where('token_hash', InvitationSender::hash($token));

        if ($lock) {
            $query->lockForUpdate();
        }

        $invitation = $token === '' ? null : $query->first();

        if ($invitation === null || in_array($invitation->status, [InvitationStatus::Revoked, InvitationStatus::Draft], true)) {
            throw self::invalid();
        }

        return $invitation;
    }

    public static function invalid(): ApiException
    {
        return new ApiException(
            errorCode: 'invitation_invalid',
            messageKey: 'competitions.errors.invitation_invalid',
            status: 404,
        );
    }

    /**
     * `s***@acme.sa`: the first character of the local part, then the domain.
     */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
