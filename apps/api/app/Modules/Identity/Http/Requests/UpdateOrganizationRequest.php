<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\OrganizationProfileData;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\Validator;

/**
 * `PATCH /organization` (API.md §1.3): any organization field of the register form except
 * `cr_number`, which is immutable (sending it → 422 on `cr_number`).
 */
final class UpdateOrganizationRequest extends IdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cr_number' => ['prohibited'],
            ...OrganizationProfileFields::rules('', partial: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cr_number.prohibited' => self::text('identity.validation.cr_number_immutable'),
            ...OrganizationProfileFields::messages(''),
        ];
    }

    /**
     * A VAT-registered organization needs a VAT number after the change.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();
                $organization = $user instanceof User ? $user->membership?->organization : null;

                $registered = $this->has('vat_registered') ? $this->boolean('vat_registered') : (bool) $organization?->vat_registered;
                $number = $this->has('vat_number') ? $this->input('vat_number') : $organization?->vat_number;

                if ($registered && ! (is_string($number) && $number !== '')) {
                    $validator->errors()->add('vat_number', self::text('identity.validation.vat_number_required'));
                }
            },
        ];
    }

    public function profileData(): OrganizationProfileData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return OrganizationProfileFields::toData($validated);
    }

    protected function prepareForValidation(): void
    {
        /** @var array<string, mixed> $input */
        $input = $this->all();
        $this->replace(OrganizationProfileFields::normalise($input));
    }

    protected function attributeKeys(): array
    {
        return OrganizationProfileFields::attributeKeys('');
    }
}
