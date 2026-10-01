<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use App\Support\Files\File;
use App\Support\Files\FileStorage;

/**
 * Removes a person from BAFO while keeping the records that reference them (ARCHITECTURE §13.8,
 * §13.10): the user is anonymised, loses every token and the membership, and is soft-deleted.
 * Device tokens are removed by Notifications on `AccountDeleted`, not here.
 */
final readonly class UserAnonymiser
{
    /**
     * Stored name of an anonymised user. The API renders `identity.deleted_user` instead.
     */
    public const string DELETED_NAME = 'Deleted user';

    public function __construct(
        private FileStorage $files,
        private AuthTokens $tokens,
    ) {}

    /**
     * Call inside a transaction.
     */
    public function anonymise(User $user): void
    {
        $this->tokens->revokeAll($user);

        $avatar = $user->avatar_file_id !== null ? File::query()->find($user->avatar_file_id) : null;

        $user->forceFill([
            'name' => self::DELETED_NAME,
            'email' => self::deletedEmail($user->public_id),
            'phone' => null,
            'password' => null,
            'avatar_file_id' => null,
            'status' => UserStatus::Deleted,
        ])->save();

        if ($avatar !== null) {
            $this->files->delete($avatar);
        }

        $user->membership()->delete();
        $user->delete();
    }

    /**
     * `deleted+{public_id}@invalid.bafo` (§13.8): unique, and never deliverable.
     */
    public static function deletedEmail(string $publicId): string
    {
        return 'deleted+'.$publicId.'@invalid.bafo';
    }
}
