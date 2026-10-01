<?php

declare(strict_types=1);

/*
| Enum value labels for the catalog module.
| Key: catalog.enums.<enum_snake>.<value>. Seeded by the schema task; the module implementer owns this file.
*/

return [
    'attributes' => [
        'kind' => 'reason type',
    ],

    'enums' => [
        'close_reason_kind' => [
            'cancel' => 'Cancellation',
            'not_awarded' => 'Closed without award',
            'award_justification' => 'Award justification',
            'void_offer' => 'Offer void',
        ],
    ],
];
