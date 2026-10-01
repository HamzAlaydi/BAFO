<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * `POST /me/avatar` (a new image) and `DELETE /me/avatar` (null): the avatar is a public-disk
 * file (png, jpg, jpeg, webp; ≤ 2 MB). The previous file is deleted.
 */
final readonly class ReplaceUserAvatar
{
    public function __construct(private FileStorage $files) {}

    public function handle(User $user, ?UploadedFile $upload, Actor $actor): User
    {
        // Checked and written before the transaction: 422 file_type_not_allowed / file_too_large.
        $new = $upload !== null
            ? $this->files->store($upload, FilePurpose::UserAvatar, $user->membership?->organization_id, $user->id)
            : null;

        return DB::transaction(function () use ($user, $new, $actor): User {
            $previous = $user->avatar_file_id !== null ? File::query()->find($user->avatar_file_id) : null;

            if ($previous === null && $new === null) {
                return $user;
            }

            $user->forceFill(['avatar_file_id' => $new?->id])->save();
            $user->setRelation('avatarFile', $new);

            if ($previous !== null) {
                $this->files->delete($previous);
            }

            AuditLogger::log($new !== null ? 'user.avatar_updated' : 'user.avatar_removed', $user, actor: $actor);

            return $user;
        });
    }
}
