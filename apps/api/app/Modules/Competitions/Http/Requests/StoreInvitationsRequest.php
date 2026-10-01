<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Http\Requests\Concerns\ValidatesInvitationRows;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * `POST /competitions/{competition}/invitations` (API.md §1.4; §3.4 bulk `{"invitations": [InvitationInput]}`):
 * 1–100 rows.
 */
final class StoreInvitationsRequest extends FormRequest
{
    use ValidatesInvitationRows;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->invitationRules(required: true);
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkInvitationTargets($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->invitationAttributes();
    }
}
