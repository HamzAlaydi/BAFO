<?php

declare(strict_types=1);

/*
| Billing module strings (plans, subscriptions, checkout, invoices, fees covered).
| Keys: billing.errors.<code>, billing.validation.*, billing.attributes.<field>,
| billing.enums.<enum_snake>.<value> (CONVENTIONS §6.2). Keep the same keys as lang/ar/billing.php.
*/

return [
    'errors' => [
        'plan_required' => 'Joining needs an active plan or a sponsored participation pass.',
        'purchase_not_available_on_platform' => 'Purchases are available on the BAFO website only.',
        'billing_profile_incomplete' => 'Complete your organisation billing details before you buy.',
        'return_url_not_allowed' => 'The return address is not allowed.',
        'plan_not_available' => 'This plan is not available.',
        'seats_out_of_range' => 'Choose between :min and :max users.',
        'subscription_downgrade_not_allowed' => 'You cannot buy a lower plan while your current plan is active.',
        'subscription_renewal_too_early' => 'You can renew in the last days of your current plan.',
        'trial_not_available' => 'The free trial is not available for your organisation.',
        'coupon_invalid' => 'This code is not valid.',
        'coupon_expired' => 'This code is not valid at this time.',
        'coupon_not_applicable' => 'This code does not apply to this purchase.',
        'coupon_exhausted' => 'This code has been used up.',
        'invoice_pdf_not_ready' => 'The invoice file is not ready yet.',
        'sponsorship_not_enabled' => 'Fees covered is not enabled for your organisation.',
        'sponsorship_locked' => 'This change is not allowed after passes were paid for.',
        'sponsorship_payment_required' => 'Pay for :count sponsored participation passes first.',
        'sponsorship_already_funded' => 'There is nothing to pay for. Publish or invite directly.',
        'invalid_webhook' => 'The webhook signature is not valid.',
        'gateway_error' => 'The payment gateway did not respond. Try again.',
        'gateway_not_configured' => 'Online payment is not available right now.',
        'invitation_cutoff_passed' => 'The time to invite participants has passed.',
    ],

    'validation' => [
        'seats_required' => 'Enter the number of users.',
        'seats_positive' => 'The number of users must be at least 1.',
        'period_invalid' => 'The end must be after the start.',
        'reason_required' => 'Enter a reason.',
        'reference_required' => 'Enter the reference.',
        'billing_profile_field_missing' => ':attribute is required for billing.',
        'item_codes' => [
            'invitation_duplicate' => 'This company is already invited.',
            'cannot_invite_own_organization' => 'You cannot invite your own organisation.',
            'vendor_blocked' => 'This vendor is blocked.',
            'vendor_not_found' => 'This vendor was not found.',
            'not_found' => 'This organisation was not found.',
        ],
    ],

    'attributes' => [
        'legal_name_ar' => 'Legal name (Arabic)',
        'cr_number' => 'CR number',
        'city' => 'City',
        'address_building_number' => 'Building number',
        'address_street' => 'Street',
        'address_district' => 'District',
        'address_postal_code' => 'Postal code',
        'vat_number' => 'VAT number',
        'plan_id' => 'Plan',
        'interval' => 'Billing period',
        'seats' => 'Users',
        'coupon_code' => 'Coupon code',
        'return_url' => 'Return address',
        'code' => 'Code',
        'purpose' => 'Purpose',
        'competition_id' => 'Competition',
        'mode' => 'Fees covered',
        'max_passes' => 'Maximum passes',
        'intent' => 'Intent',
        'invitations' => 'Invitations',
        'invitations.email' => 'Email',
        'invitations.organization_id' => 'Organisation',
        'invitations.vendor_id' => 'Vendor',
        'invitations.name' => 'Contact name',
    ],

    'lines' => [
        'plan' => ':plan plan, :interval',
        'custom_seats' => ':plan plan, :seats users, :interval',
        'sponsored_pass' => 'Sponsored participation pass, :reference',
    ],

    'gateway' => [
        'description' => 'BAFO order :id',
    ],

    'voucher' => [
        'reason' => 'Unused passes :reference',
    ],

    'fake_pay' => [
        'title' => 'BAFO test payment',
        'test_mode' => 'Test mode',
        'merchant' => 'You are paying BAFO. No real money moves on this page.',
        'subtotal' => 'Subtotal',
        'credit' => 'Credit from your current plan',
        'discount' => 'Discount',
        'vat' => 'VAT :rate%',
        'total' => 'Total',
        'approve' => 'Approve payment',
        'decline' => 'Decline',
        'declined_message' => 'The payment was declined on the test page.',
        'not_pending' => 'This payment is already processed (:status).',
        'hint' => 'Prices exclude VAT; VAT is added above.',
    ],

    'invoice' => [
        'title' => 'Tax invoice',
        'number' => 'Invoice number',
        'issue_date' => 'Issue date',
        'supply_date' => 'Supply date',
        'issued_at' => 'Issued at',
        'seller' => 'Seller',
        'buyer' => 'Buyer',
        'vat_number' => 'VAT number',
        'cr_number' => 'CR number',
        'description' => 'Description',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit price',
        'net' => 'Amount',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'taxable' => 'Taxable amount',
        'vat' => 'VAT :rate%',
        'total' => 'Total incl. VAT',
    ],

    'enums' => [
        'entitlement_source' => [
            'plan' => 'Plan',
            'sponsored_pass' => 'Sponsored participation pass',
            'grant' => 'Grant',
        ],
        'coupon_kind' => [
            'coupon' => 'Coupon',
            'voucher' => 'Voucher',
        ],
        'discount_type' => [
            'percent' => 'Percent',
            'fixed' => 'Fixed amount',
        ],
        'coupon_scope' => [
            'any' => 'Any purchase',
            'subscription' => 'Subscriptions',
            'sponsorship' => 'Fees covered',
        ],
        'payment_purpose' => [
            'subscription' => 'Subscription',
            'sponsorship' => 'Fees covered',
        ],
        'payment_status' => [
            'pending' => 'Pending',
            'succeeded' => 'Succeeded',
            'failed' => 'Failed',
            'expired' => 'Expired',
            'refunded' => 'Refunded',
        ],
        'payment_line_kind' => [
            'plan' => 'Plan',
            'custom_seats' => 'Custom seats',
            'sponsored_pass' => 'Sponsored participation pass',
        ],
        'subscription_source' => [
            'paid' => 'Paid',
            'trial' => 'Trial',
            'grant' => 'Grant',
        ],
        'billing_interval' => [
            'monthly' => 'Monthly',
            'annual' => 'Annual',
        ],
        'subscription_status' => [
            'pending_payment' => 'Pending payment',
            'active' => 'Active',
            'superseded' => 'Superseded',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        ],
        'sponsorship_mode' => [
            'all' => 'All invitees',
            'selected' => 'Selected invitees',
        ],
        'sponsorship_status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'settled' => 'Settled',
        ],
        'pass_source' => [
            'purchase' => 'Purchase',
            'freed_slot' => 'Freed slot',
            'admin_grant' => 'Admin grant',
        ],
        'pass_status' => [
            'pending' => 'Pending',
            'reserved' => 'Reserved',
            'joined' => 'Joined',
            'released' => 'Released',
            'unused' => 'Unused',
            'void' => 'Void',
        ],
        'pass_release_reason' => [
            'declined' => 'Declined',
            'revoked' => 'Invitation revoked',
            'covered_by_own_plan' => 'Covered by own plan',
            'duplicate_organization' => 'Duplicate organisation',
        ],
        'invoice_type' => [
            'tax_invoice' => 'Tax invoice',
            'credit_note' => 'Credit note',
        ],
        'e_invoice_status' => [
            'pending' => 'Pending',
            'cleared' => 'Cleared',
            'reported' => 'Reported',
            'rejected' => 'Rejected',
            'failed' => 'Failed',
        ],
        'coverage' => [
            'sponsored' => 'Fees covered',
            'own_plan' => 'Own plan',
            'none' => 'Not covered',
        ],
        'access_state' => [
            'join_required' => 'Join to take part',
            'plan_required' => 'Plan required',
            'full' => 'Full access',
            'read_only' => 'Read only',
            'unavailable' => 'Unavailable',
        ],
        'quote_line_reason' => [
            'not_selected' => 'Not selected',
            'cap_reached' => 'Pass limit reached',
            'own_plan' => 'Covered by own plan',
        ],
        'sponsorship_intent' => [
            'publish' => 'Publish',
            'invite' => 'Invite',
        ],
        'gateway_payment_status' => [
            'paid' => 'Paid',
            'failed' => 'Failed',
            'pending' => 'Pending',
        ],
        'purchase_kind' => [
            'new' => 'New subscription',
            'renewal' => 'Renewal',
            'upgrade' => 'Upgrade',
        ],
    ],
];
