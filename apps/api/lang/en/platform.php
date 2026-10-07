<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Platform module (contact form, legal documents, files, settings)
|--------------------------------------------------------------------------
|
| Same keys as lang/ar/platform.php. Generic error codes live in errors.php.
|
*/

return [

    'attributes' => [
        'name' => 'name',
        'email' => 'email address',
        'phone' => 'mobile number',
        'company' => 'organisation',
        'subject' => 'subject',
        'message' => 'message',
        'website_url' => 'website',
        'file' => 'file',
    ],

    'validation' => [
        'phone' => 'Enter a Saudi mobile number in the format +9665XXXXXXXX.',
    ],

    'enums' => [
        'legal_document_code' => [
            'terms' => 'Terms and conditions',
            'privacy' => 'Privacy policy',
            'refund' => 'Refund policy',
            'competition_rules' => 'Competition rules',
            'api_terms' => 'API terms of use',
        ],
        'contact_status' => [
            'new' => 'New',
            'read' => 'Read',
            'archived' => 'Archived',
        ],
        'file_purpose' => [
            'organization_logo' => 'Organisation logo',
            'organization_profile' => 'Organisation profile',
            'user_avatar' => 'Profile picture',
            'competition_attachment' => 'Competition attachment',
            'invoice_pdf' => 'Invoice',
            'competition_report' => 'Competition report',
            'import_source' => 'Import file',
            'import_errors' => 'Import errors',
            'export' => 'Export',
        ],
        // Setting platform.release_scope (RELEASE_SCOPE.md §1.1).
        'release_scope' => [
            'core' => 'Core (tenders and auctions)',
            'full' => 'Full (every feature)',
        ],
    ],

    'legal' => [
        'draft_notice' => 'Draft text – counsel to provide',
        'placeholder_body' => 'This placeholder will be replaced by the text approved by legal counsel.',
    ],

];
