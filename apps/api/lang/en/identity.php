<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Identity module (registration, OTP, sign-in, profile, organization, team, account deletion)
|--------------------------------------------------------------------------
|
| Same keys as lang/ar/identity.php. Generic error codes live in errors.php.
|
*/

return [

    'errors' => [
        'invalid_credentials' => 'The e-mail address or password is incorrect.',
        'email_not_verified' => 'Verify your e-mail address to continue. We sent you a verification code.',
        'account_inactive' => 'This account is not active. Contact your organisation administrator.',
        'organization_suspended' => 'Your organisation is suspended. Contact BAFO support.',
        'otp_invalid' => 'The code is incorrect.',
        'otp_expired' => 'The code has expired or was already used. Request a new code.',
        'otp_too_many_attempts' => 'The code was cancelled after too many attempts. Request a new code.',
        'otp_resend_cooldown' => 'Please wait :seconds seconds before requesting a new code.',
        'password_incorrect' => 'The current password is incorrect.',
        'team_invitation_invalid' => 'This invitation link is invalid or has expired. Ask your account administrator to send it again.',
        'seat_limit_reached' => 'All the seats of your plan are in use. Upgrade the plan or remove a member first.',
        'cannot_modify_owner' => 'The account owner cannot be changed or removed.',
        'cannot_modify_self' => 'You cannot change or remove your own membership.',
        'account_deletion_blocked' => 'The account cannot be deleted while it has open competitions or participations.',
        'account_deletion_pending' => 'An account deletion request is already pending.',
        'invitation_email_mismatch' => 'Register with the e-mail address the invitation was sent to.',
    ],

    'validation' => [
        'email_taken' => 'An account with this e-mail address already exists.',
        'cr_number_taken' => 'An organisation with this commercial registration number is already registered.',
        'cr_number' => 'The commercial registration number must be exactly 10 digits.',
        'cr_number_immutable' => 'The commercial registration number cannot be changed.',
        'phone' => 'Enter a Saudi mobile number in the format +9665XXXXXXXX.',
        'vat_number' => 'The VAT number must be 15 digits that start and end with 3.',
        'vat_number_required' => 'Enter the VAT number of a VAT-registered organisation.',
        'website' => 'Enter a website address that starts with https://.',
        'four_digits' => 'This field must be exactly 4 digits.',
        'postal_code' => 'The postal code must be exactly 5 digits.',
        'short_address' => 'The short address must be 4 capital letters followed by 4 digits, for example RRRD2929.',
        'invitation_token_invalid' => 'This invitation is invalid or no longer available.',
        'flag_not_held' => 'You can only grant a permission that you hold yourself.',
    ],

    'attributes' => [
        'name' => 'name',
        'email' => 'e-mail address',
        'phone' => 'mobile number',
        'password' => 'password',
        'current_password' => 'current password',
        'locale' => 'language',
        'code' => 'verification code',
        'purpose' => 'code purpose',
        'device_name' => 'device name',
        'token' => 'invitation token',
        'invitation_token' => 'invitation token',
        'accept_terms' => 'terms and conditions',
        'accept_privacy' => 'privacy policy',
        'website_url' => 'website',
        'file' => 'file',
        'role' => 'role',
        'can_award' => 'award permission',
        'can_purchase' => 'purchase permission',
        'status' => 'status',
        'reason' => 'reason',
        'organization' => [
            'self' => 'organisation',
            'name' => 'organisation name',
            'cr_number' => 'commercial registration number',
            'region_id' => 'region',
            'city' => 'city',
            'vat_registered' => 'VAT registration',
            'vat_number' => 'VAT number',
            'legal_name_ar' => 'legal name (Arabic)',
            'legal_name_en' => 'legal name (English)',
            'website' => 'website',
            'category_ids' => 'categories',
            'visible_in_suggestions' => 'visibility in issuer suggestions',
            'national_address' => [
                'self' => 'national address',
                'building_number' => 'building number',
                'street' => 'street',
                'district' => 'district',
                'postal_code' => 'postal code',
                'additional_number' => 'additional number',
                'short_address' => 'short address',
            ],
        ],
    ],

    'deleted_user' => 'Deleted user',
    'deleted_organization' => 'Deleted organisation',

    'mail' => [
        'otp' => [
            'subject' => [
                'email_verification' => 'Your BAFO verification code',
                'password_reset' => 'Your BAFO password reset code',
                'invitation_claim' => 'Your BAFO invitation code',
            ],
            'heading' => 'Your verification code',
            'intro' => [
                'email_verification' => 'Use this code to verify your e-mail address on BAFO.',
                'password_reset' => 'Use this code to set a new password for your BAFO account.',
                'invitation_claim' => 'Use this code to confirm the competition invitation sent to this e-mail address.',
            ],
            'expires' => 'The code expires in :minutes minutes and can be used once.',
            'ignore' => 'If you did not request this code, you can ignore this e-mail.',
        ],
        'team_invitation' => [
            'subject' => 'You are invited to join :organization on BAFO',
            'greeting' => 'Hello :name,',
            'intro' => ':inviter invited you to join :organization on BAFO as :role.',
            'action' => 'Accept the invitation',
            'expires' => 'The invitation is valid until :date.',
            'ignore' => 'If you were not expecting this invitation, you can ignore this e-mail.',
        ],
        'account_deletion' => [
            'subject' => 'Your BAFO account deletion is scheduled',
            'greeting' => 'Hello :name,',
            'intro' => [
                'user' => 'We received your request to delete your BAFO account. It will be deleted on :date.',
                'organization' => 'We received your request to delete your organisation account on BAFO, with all its users. It will be deleted on :date.',
            ],
            'cancel' => 'To keep your account, sign in and cancel the request before that date.',
        ],
    ],

    'enums' => [
        'organization_status' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'deleted' => 'Deleted',
        ],
        'user_status' => [
            'active' => 'Active',
            'pending_verification' => 'Pending verification',
            'deleted' => 'Deleted',
        ],
        'org_role' => [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'member' => 'Member',
        ],
        'membership_status' => [
            'invited' => 'Invited',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'otp_purpose' => [
            'email_verification' => 'Email verification',
            'password_reset' => 'Password reset',
            'invitation_claim' => 'Invitation claim',
        ],
        'deletion_scope' => [
            'user' => 'User account',
            'organization' => 'Organisation account',
        ],
        'deletion_status' => [
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
        ],
        'permission' => [
            'organization' => [
                'update' => 'Update organisation',
            ],
            'team' => [
                'manage' => 'Manage team',
            ],
            'billing' => [
                'view' => 'View billing',
                'purchase' => 'Make purchases',
            ],
            'competitions' => [
                'create' => 'Create competitions',
                'manage_all' => 'Manage all competitions',
                'award' => 'Award competitions',
            ],
            'participation' => [
                'submit_offers' => 'Submit offers',
            ],
            'integrations' => [
                'manage' => 'Manage integrations',
            ],
            'account' => [
                'delete_organization' => 'Delete the organisation account',
            ],
        ],
    ],

];
