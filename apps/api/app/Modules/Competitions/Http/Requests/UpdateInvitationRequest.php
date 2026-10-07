<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Models\Invitation;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use App\Support\Features\FeatureRefusals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * `PATCH /competitions/{competition}/invitations/{invitation}` (API.md §1.4): `name`, `sponsored`.
 * While the release scope hides `sponsorship` (RELEASE_SCOPE.md §1.5), `sponsored = true` is a
 * 422 `errors.feature_disabled_field` unless the invitation is already sponsored.
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
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $invitation = $this->route('invitation');

                if (! app(FeatureFlags::class)->enabled(Feature::Sponsorship)
                    && in_array($this->input('sponsored'), [true, 1, '1'], true)
                    && ! ($invitation instanceof Invitation && $invitation->sponsored_requested)) {
                    FeatureRefusals::refuseField($validator, 'sponsored');
                }
            },
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
