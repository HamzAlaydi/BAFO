<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\Membership;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Membership (API.md §2.3), with the embedded User. The owner is always `can_award` and
 * `can_purchase` (§5.3).
 *
 * @mixin Membership
 */
final class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->isOwner();

        return [
            'id' => $this->public_id,
            'role' => $this->role->value,
            'can_award' => $owner || $this->can_award,
            'can_purchase' => $owner || $this->can_purchase,
            'status' => $this->status->value,
            'joined_at' => Iso::format($this->joined_at),
            // CONTRACT-GAP: API.md §2.3 names `invited_at` without a column; it is the creation
            // time of memberships made by a team invitation, null for the registering owner.
            'invited_at' => $this->invited_by_user_id !== null ? Iso::format($this->created_at) : null,
            'user' => (new UserResource($this->user))->resolve($request),
        ];
    }
}
