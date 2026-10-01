<?php

declare(strict_types=1);

/*
| Enum value labels for the integrations module.
| Key: integrations.enums.<enum_snake>.<value>. Owned by the Integrations module.
*/

return [
    'enums' => [
        'vendor_status' => [
            'active' => 'Active',
            'blocked' => 'Blocked',
            'archived' => 'Archived',
        ],
        'vendor_source' => [
            'web' => 'Dashboard',
            'api' => 'API',
            'import' => 'Import',
        ],
        'api_client_status' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'revoked' => 'Revoked',
        ],
        'webhook_endpoint_status' => [
            'active' => 'Active',
            'disabled' => 'Disabled',
        ],
        'webhook_disabled_reason' => [
            'manual' => 'Manual',
            'failing' => 'Failing',
        ],
        'delivery_status' => [
            'pending' => 'Pending',
            'succeeded' => 'Succeeded',
            'failed' => 'Failed',
        ],
        'import_type' => [
            'vendors' => 'Vendors',
        ],
        'import_mode' => [
            'validate' => 'Validate',
            'commit' => 'Commit',
        ],
        'job_status' => [
            'queued' => 'Queued',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ],
        'export_type' => [
            'results' => 'Results',
            'offer_log' => 'Offer log',
            'awards' => 'Awards',
            'vendors' => 'Vendors',
        ],
        'export_format' => [
            'csv' => 'CSV',
            'xlsx' => 'Excel (XLSX)',
        ],
        'api_scope' => [
            'organization:read' => 'Read organisation',
            'lookups:read' => 'Read lookups',
            'vendors:read' => 'Read vendors',
            'vendors:write' => 'Write vendors',
            'competitions:read' => 'Read competitions',
            'competitions:write' => 'Write competitions',
            'competitions:publish' => 'Publish competitions',
            'competitions:manage' => 'Manage competitions',
            'invitations:read' => 'Read invitations',
            'invitations:write' => 'Write invitations',
            'offers:read' => 'Read offers',
            'awards:read' => 'Read awards',
            'awards:sync' => 'Sync awards',
            'webhooks:manage' => 'Manage webhooks',
        ],
        'webhook_event_type' => [
            'competition' => [
                'published' => 'A competition was published',
                'extended' => 'A competition\'s closing time was extended',
                'closed' => 'Offers closed and the competition is in evaluation',
                'offers_opened' => 'The offers of a sealed competition were opened',
                'cancelled' => 'A competition was cancelled',
                'not_awarded' => 'A competition was closed without award',
            ],
            'invitation' => [
                'accepted' => 'An invited company joined a competition',
                'declined' => 'An invited company declined an invitation',
            ],
            'offer' => [
                'submitted' => 'A participant submitted its first offer',
                'updated' => 'A participant submitted a new offer',
            ],
            'award' => [
                'issued' => 'A competition was awarded',
                'cancelled' => 'An award was revoked',
            ],
            'webhook' => [
                'test' => 'Test event sent from the dashboard or the API',
            ],
        ],
        'import_row_error_code' => [
            'required' => 'Required',
            'invalid_format' => 'Invalid format',
            'unknown_region' => 'Unknown region',
            'unknown_category' => 'Unknown category',
            'duplicate_in_file' => 'Duplicate in the file',
            'vendor_email_taken' => 'E-mail used by another vendor',
        ],
        'api_auth_method' => [
            'oauth' => 'OAuth access token',
            'api_key' => 'API key',
        ],
    ],

    'errors' => [
        'invalid_token' => 'The access token or API key is invalid or expired.',
        'invalid_client' => 'Client authentication failed.',
        'unsupported_grant_type' => 'Only the client_credentials grant is supported.',
        'invalid_scope' => 'The requested scope is not allowed for this client.',
        'insufficient_scope' => 'This request needs the :scope scope.',
        'api_access_disabled' => 'API access is not enabled for your organisation. Contact BAFO to enable it.',
        'external_ref_conflict' => 'This ERP key is already used by another record.',
        'vendor_email_taken' => 'A vendor with this e-mail already exists in your directory.',
        'webhook_url_invalid' => 'The URL must use https and point to a public address.',
    ],

    'validation' => [
        'phone' => 'Enter a Saudi mobile number in the format +9665XXXXXXXX.',
        'cr_number' => 'The CR number must be exactly 10 digits.',
        'vat_number' => 'The VAT number must be 15 digits, starting and ending with 3.',
        'external_system' => 'The system must be a lowercase slug such as sap_s4 or custom:name.',
        'external_type' => 'The type must be a lowercase slug such as supplier.',
        'event_type' => 'Choose event types from the catalogue, or * for all.',
    ],

    'attributes' => [
        'name' => 'name',
        'name_en' => 'English name',
        'email' => 'e-mail',
        'contact_name' => 'contact name',
        'phone' => 'phone',
        'cr_number' => 'CR number',
        'vat_number' => 'VAT number',
        'region_id' => 'region',
        'region_code' => 'region',
        'city' => 'city',
        'category_ids' => 'categories',
        'category_codes' => 'categories',
        'status' => 'status',
        'notes' => 'notes',
        'external_refs' => 'ERP keys',
        'system' => 'system',
        'external_id' => 'ERP key',
        'description' => 'description',
        'scopes' => 'scopes',
        'expires_in_days' => 'validity in days',
        'url' => 'URL',
        'event_types' => 'event types',
        'file' => 'file',
        'type' => 'type',
        'mode' => 'mode',
        'format' => 'format',
        'competition_id' => 'competition',
        'from' => 'from date',
        'to' => 'to date',
    ],

    'import' => [
        'row_errors' => [
            'required' => 'The :column column is required.',
            'invalid_format' => 'The :column value has an invalid format.',
            'unknown_region' => 'Unknown region code.',
            'unknown_category' => 'One or more category codes are unknown.',
            'duplicate_in_file' => 'This vendor appears more than once in the file.',
            'vendor_email_taken' => 'Another vendor in your directory already uses this e-mail.',
        ],
        'failures' => [
            'missing_columns' => 'The file must have these columns: :columns.',
            'too_many_rows' => 'The file has more than :max rows.',
            'empty_file' => 'The file has no header row.',
            'unreadable' => 'The file could not be read. Use the template in CSV (UTF-8) or XLSX format.',
        ],
        'template' => [
            'columns' => [
                'external_system' => 'ERP system slug, for example sap_s4, oracle_fusion, odoo or custom:name. Give it with external_id.',
                'external_id' => 'The supplier key in your ERP. With external_system, it identifies the vendor on re-import.',
                'name' => 'Legal or trading name.',
                'name_en' => 'English name.',
                'cr_number' => 'Commercial registration: 10 digits.',
                'vat_number' => 'VAT number: 15 digits, starting and ending with 3.',
                'email' => 'Contact e-mail where invitations are sent. Unique in your directory.',
                'contact_name' => 'Contact person.',
                'phone' => 'Saudi mobile in the format +9665XXXXXXXX.',
                'region_code' => 'Region code from the Lists sheet, for example RIY.',
                'city' => 'City.',
                'category_codes' => 'Category codes from the Lists sheet, separated by ;',
                'status' => 'active or blocked (blocked vendors cannot be invited). Empty means active.',
            ],
            'notes' => 'Keep the header row. Rows are matched by external_system + external_id when both are given, otherwise by e-mail. Validate first, then import the valid rows.',
        ],
    ],

    'exports' => [
        'failed' => 'The export could not be generated. Please try again.',
    ],

    'docs' => [
        'title' => 'BAFO Public API v1',
        'description' => 'Reference of the BAFO public API for ERP integrations: OAuth2 client credentials or API keys, vendors, competitions, invitations, offers, awards and webhooks.',
    ],
];
