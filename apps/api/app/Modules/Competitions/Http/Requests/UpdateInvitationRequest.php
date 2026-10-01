<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH /competitions/{competition}/invitations/{invitation}` (API.md §1.4): `name`, `sponsored`.
 */
final class UpdateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'sponsored' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => self::trans('competitions.attributes.name'),
            'sponsored' => self::trans('competitions.attributes.sponsored'),
        ];
    }

    /**
     * @return array{name?: string|null, sponsored?: bool}
     */
    public function changes(): array
    {
        $changes = [];
        $data = $this->validated();

        if (array_key_exists('name', $data)) {
            $changes['name'] = is_string($data['name']) ? $data['name'] : null;
        }

        if (array_key_exists('sponsored', $data)) {
            $changes['sponsored'] = (bool) $data['sponsored'];
        }

        return $changes;
    }

    private static function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
