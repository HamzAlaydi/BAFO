<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Data\OrganizationProfileData;
use App\Modules\Identity\Data\RegistrationData;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `POST /auth/register` (API.md §1.3). `website_url` is the honeypot. An unknown or expired
 * `invitation_token` is a field error; a valid one for another e-mail is the business error
 * `invitation_email_mismatch` (raised by the Action).
 */
final class RegisterRequest extends IdentityRequest
{
    private ?Invitation $invitation = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
            'password' => ['required', 'string', 'confirmed', self::passwordRule()],
            'locale' => ['nullable', 'string', Rule::in(['ar', 'en'])],
            'organization' => ['required', 'array'],
            'organization.cr_number' => ['required', 'string', 'regex:'.OrganizationProfileFields::CR_PATTERN, Rule::unique(Organization::class, 'cr_number')],
            ...OrganizationProfileFields::rules('organization.', partial: false),
            'accept_terms' => ['required', 'accepted'],
            'accept_privacy' => ['required', 'accepted'],
            'invitation_token' => ['nullable', 'string', 'max:255'],
            'website_url' => ['prohibited'],
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
            'organization.cr_number.regex' => self::text('identity.validation.cr_number'),
            'organization.cr_number.unique' => self::text('identity.validation.cr_number_taken'),
            ...OrganizationProfileFields::messages('organization.'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $token = $this->input('invitation_token');

                if (! is_string($token) || $token === '' || $validator->errors()->has('invitation_token')) {
                    return;
                }

                $this->invitation = Invitation::query()
                    ->where('token_hash', hash('sha256', $token))
                    ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
                    ->first();

                if ($this->invitation === null) {
                    $validator->errors()->add('invitation_token', self::text('identity.validation.invitation_token_invalid'));
                }
            },
        ];
    }

    public function registrationData(): RegistrationData
    {
        /** @var array<string, mixed> $organization */
        $organization = $this->validated('organization');
        $locale = $this->validated('locale');
        $profile = OrganizationProfileFields::toData($organization);

        return new RegistrationData(
            name: trim($this->validatedString('name')),
            email: $this->validatedString('email'),
            phone: $this->validatedString('phone'),
            password: $this->validatedString('password'),
            locale: is_string($locale) ? $locale : App::getLocale(),
            organization: new OrganizationProfileData(
                [...$profile->attributes, 'cr_number' => $organization['cr_number']],
                $profile->categoryIds,
            ),
            invitationEmail: $this->invitation?->email,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseEmail();

        $organization = $this->input('organization');

        if (is_array($organization)) {
            /** @var array<string, mixed> $organization */
            $this->merge(['organization' => OrganizationProfileFields::normalise($organization)]);
        }
    }

    protected function attributeKeys(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'password' => 'password',
            'locale' => 'locale',
            'organization' => 'organization.self',
            ...OrganizationProfileFields::attributeKeys('organization.'),
            'accept_terms' => 'accept_terms',
            'accept_privacy' => 'accept_privacy',
            'invitation_token' => 'invitation_token',
            'website_url' => 'website_url',
        ];
    }
}
