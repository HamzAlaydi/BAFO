<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use Illuminate\Validation\Rule;

/**
 * `PATCH /team/members/{membership}` (API.md §1.3): each field optional.
 */
final class UpdateTeamMemberRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', 'string', Rule::in([OrgRole::Admin->value, OrgRole::Member->value])],
            'can_award' => ['sometimes', 'required', 'boolean'],
            'can_purchase' => ['sometimes', 'required', 'boolean'],
            'status' => ['sometimes', 'required', 'string', Rule::in([MembershipStatus::Active->value, MembershipStatus::Inactive->value])],
        ];
    }

    /**
     * @return array{role?: string, can_award?: bool, can_purchase?: bool, status?: string}
     */
    public function changes(): array
    {
        $changes = [];

        foreach (['role', 'status'] as $field) {
            if ($this->has($field)) {
                $changes[$field] = $this->validatedString($field);
            }
        }

        foreach (['can_award', 'can_purchase'] as $field) {
            if ($this->has($field)) {
                $changes[$field] = $this->boolean($field);
            }
        }

        /** @var array{role?: string, can_award?: bool, can_purchase?: bool, status?: string} $changes */
        return $changes;
    }

    protected function attributeKeys(): array
    {
        return ['role' => 'role', 'can_award' => 'can_award', 'can_purchase' => 'can_purchase', 'status' => 'status'];
    }
}
