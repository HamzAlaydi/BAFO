<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use App\Support\Files\FileStorage;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User (API.md §2.1). A deleted user's name renders as `identity.deleted_user`.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatar = $this->avatarFile;
        $deleted = $this->status === UserStatus::Deleted;
        $deletedName = __('identity.deleted_user');

        return [
            'id' => $this->public_id,
            'name' => $deleted && is_string($deletedName) ? $deletedName : $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'avatar_url' => $avatar !== null ? app(FileStorage::class)->publicUrl($avatar) : null,
            'status' => $this->status->value,
            'email_verified_at' => Iso::format($this->email_verified_at),
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
