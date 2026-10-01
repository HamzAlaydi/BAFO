<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

/**
 * Validated `POST /auth/register` input (API.md §1.3).
 */
final readonly class RegistrationData
{
    /**
     * @param  string|null  $invitationEmail  the e-mail of the competition invitation whose token
     *                                        was sent (the token itself is valid), else null
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
        public string $locale,
        public OrganizationProfileData $organization,
        public ?string $invitationEmail,
    ) {}
}
