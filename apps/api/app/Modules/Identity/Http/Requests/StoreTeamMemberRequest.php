<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\TeamMemberData;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\Rule;

/**
 * `POST /team/members` (API.md §1.3). An e-mail that already belongs to a user is a field error
 * on `email`.
 */
final class StoreTeamMemberRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'role' => ['required', 'string', Rule::in([OrgRole::Admin->value, OrgRole::Member->value])],
            'can_award' => ['nullable', 'boolean'],
            'can_purchase' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => self::text('identity.validation.email_taken'),
            'phone.regex' => self::text('identity.validation.phone'),
        ];
    }

    public function memberData(): TeamMemberData
    {
        $phone = $this->validated('phone');

        return new TeamMemberData(
            name: trim($this->validatedString('name')),
            email: $this->validatedString('email'),
            phone: is_string($phone) ? $phone : null,
            role: OrgRole::from($this->validatedString('role')),
            canAward: $this->validated('can_award') === null ? null : $this->boolean('can_award'),
            canPurchase: $this->validated('can_purchase') === null ? null : $this->boolean('can_purchase'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmail();
    }

    protected function attributeKeys(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'role' => 'role',
            'can_award' => 'can_award',
            'can_purchase' => 'can_purchase',
        ];
    }
}
