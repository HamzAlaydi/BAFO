<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\MembershipStatus;
use Illuminate\Validation\Rule;

/**
 * `GET /team/members?status=` (API.md §1.3).
 */
final class ListTeamMembersRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(MembershipStatus::class)],
        ];
    }

    public function status(): ?MembershipStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? MembershipStatus::from($status) : null;
    }

    protected function attributeKeys(): array
    {
        return ['status' => 'status'];
    }
}
