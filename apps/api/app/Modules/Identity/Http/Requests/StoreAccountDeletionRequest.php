<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

/**
 * `POST /account/deletion` (API.md §1.3). A wrong password is the business error
 * `password_incorrect` (raised by the Action).
 */
final class StoreAccountDeletionRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function password(): string
    {
        return $this->validatedString('password');
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    protected function attributeKeys(): array
    {
        return ['password' => 'password', 'reason' => 'reason'];
    }
}
