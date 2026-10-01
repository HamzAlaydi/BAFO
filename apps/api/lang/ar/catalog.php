<?php

declare(strict_types=1);

/*
| تسميات قيم الحقول المعدودة (enums) لوحدة catalog.
| المفتاح: catalog.enums.<enum_snake>.<value> — بدأها مهندس المخطط، ويملك الملفَّ منفِّذ الوحدة.
*/

return [
    'attributes' => [
        'kind' => 'نوع السبب',
    ],

    'enums' => [
        'close_reason_kind' => [
            'cancel' => 'إلغاء المنافسة',
            'not_awarded' => 'إغلاق دون ترسية',
            'award_justification' => 'مبرر الترسية',
            'void_offer' => 'إلغاء عرض',
        ],
    ],
];
