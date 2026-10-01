<?php

declare(strict_types=1);

/*
| تسميات قيم الحقول المعدودة (enums) لوحدة integrations.
| المفتاح: integrations.enums.<enum_snake>.<value> — بدأها مهندس المخطط، ويملك الملفَّ منفِّذ الوحدة.
*/

return [
    'enums' => [
        'vendor_status' => [
            'active' => 'نشط',
            'blocked' => 'محظور',
            'archived' => 'مؤرشف',
        ],
        'vendor_source' => [
            'web' => 'لوحة التحكم',
            'api' => 'واجهة البرمجة',
            'import' => 'استيراد',
        ],
        'api_client_status' => [
            'active' => 'نشط',
            'suspended' => 'موقوف',
            'revoked' => 'ملغى',
        ],
        'webhook_endpoint_status' => [
            'active' => 'نشط',
            'disabled' => 'معطّل',
        ],
        'webhook_disabled_reason' => [
            'manual' => 'يدوياً',
            'failing' => 'إخفاقات متكررة',
        ],
        'delivery_status' => [
            'pending' => 'قيد الانتظار',
            'succeeded' => 'ناجح',
            'failed' => 'فاشل',
        ],
        'import_type' => [
            'vendors' => 'الموردون',
        ],
        'import_mode' => [
            'validate' => 'تحقق فقط',
            'commit' => 'اعتماد',
        ],
        'job_status' => [
            'queued' => 'في قائمة الانتظار',
            'processing' => 'قيد المعالجة',
            'completed' => 'مكتمل',
            'failed' => 'فشل',
        ],
        'export_type' => [
            'results' => 'النتائج',
            'offer_log' => 'سجل العروض',
            'awards' => 'الترسيات',
            'vendors' => 'الموردون',
        ],
        'export_format' => [
            'csv' => 'CSV',
            'xlsx' => 'Excel (XLSX)',
        ],
        'api_scope' => [
            'organization:read' => 'قراءة بيانات المنشأة',
            'lookups:read' => 'قراءة القوائم المرجعية',
            'vendors:read' => 'قراءة الموردين',
            'vendors:write' => 'إضافة الموردين وتعديلهم',
            'competitions:read' => 'قراءة المنافسات',
            'competitions:write' => 'إنشاء المنافسات وتعديلها',
            'competitions:publish' => 'نشر المنافسات',
            'competitions:manage' => 'إدارة المنافسات (التمديد والإلغاء والإغلاق دون ترسية)',
            'invitations:read' => 'قراءة الدعوات',
            'invitations:write' => 'إرسال الدعوات وإدارتها',
            'offers:read' => 'قراءة العروض',
            'awards:read' => 'قراءة الترسيات',
            'awards:sync' => 'مزامنة الترسيات مع نظام تخطيط الموارد',
            'webhooks:manage' => 'إدارة نقاط Webhook',
        ],
        'webhook_event_type' => [
            'competition' => [
                'published' => 'نُشرت منافسة',
                'extended' => 'مُدِّد موعد إغلاق منافسة',
                'closed' => 'أُغلق تقديم العروض والمنافسة قيد التقييم',
                'offers_opened' => 'فُتحت عروض منافسة مغلقة الظرف',
                'cancelled' => 'أُلغيت منافسة',
                'not_awarded' => 'أُغلقت منافسة دون ترسية',
            ],
            'invitation' => [
                'accepted' => 'انضمت منشأة مدعوة إلى منافسة',
                'declined' => 'اعتذرت منشأة مدعوة عن دعوة',
            ],
            'offer' => [
                'submitted' => 'قدّم متنافس عرضه الأول',
                'updated' => 'قدّم متنافس عرضاً جديداً',
            ],
            'award' => [
                'issued' => 'تمت ترسية منافسة',
                'cancelled' => 'أُلغيت ترسية',
            ],
            'webhook' => [
                'test' => 'حدث تجريبي من لوحة التحكم أو واجهة البرمجة',
            ],
        ],
        'import_row_error_code' => [
            'required' => 'حقل مطلوب',
            'invalid_format' => 'صيغة غير صحيحة',
            'unknown_region' => 'منطقة غير معروفة',
            'unknown_category' => 'فئة غير معروفة',
            'duplicate_in_file' => 'مكرر في الملف',
            'vendor_email_taken' => 'البريد مستخدم لمورد آخر',
        ],
        'api_auth_method' => [
            'oauth' => 'رمز وصول OAuth',
            'api_key' => 'مفتاح واجهة برمجة',
        ],
    ],

    'errors' => [
        'invalid_token' => 'رمز الوصول أو مفتاح واجهة البرمجة غير صالح أو منتهي الصلاحية.',
        'invalid_client' => 'تعذّر التحقق من هوية العميل.',
        'unsupported_grant_type' => 'نوع المنح المدعوم هو client_credentials فقط.',
        'invalid_scope' => 'الصلاحية المطلوبة غير مسموح بها لهذا العميل.',
        'insufficient_scope' => 'يتطلب هذا الطلب الصلاحية :scope.',
        'api_access_disabled' => 'واجهة برمجة التطبيقات غير مفعّلة لمنشأتكم. تواصلوا مع بافو لتفعيلها.',
        'external_ref_conflict' => 'هذا المعرّف في نظام تخطيط الموارد مستخدم لسجل آخر.',
        'vendor_email_taken' => 'يوجد في دليلكم مورد بهذا البريد الإلكتروني.',
        'webhook_url_invalid' => 'يجب أن يكون الرابط https وعلى عنوان عام.',
    ],

    'validation' => [
        'phone' => 'أدخل رقم جوال سعودياً بالصيغة +9665XXXXXXXX.',
        'cr_number' => 'يجب أن يتكون رقم السجل التجاري من 10 أرقام.',
        'vat_number' => 'يجب أن يتكون الرقم الضريبي من 15 رقماً يبدأ وينتهي بالرقم 3.',
        'external_system' => 'يجب أن يكون اسم النظام بحروف لاتينية صغيرة مثل sap_s4 أو custom:name.',
        'external_type' => 'يجب أن يكون النوع بحروف لاتينية صغيرة مثل supplier.',
        'event_type' => 'اختر أنواع الأحداث من القائمة، أو * لجميعها.',
    ],

    'attributes' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم بالإنجليزية',
        'email' => 'البريد الإلكتروني',
        'contact_name' => 'اسم جهة الاتصال',
        'phone' => 'الجوال',
        'cr_number' => 'رقم السجل التجاري',
        'vat_number' => 'الرقم الضريبي',
        'region_id' => 'المنطقة',
        'region_code' => 'المنطقة',
        'city' => 'المدينة',
        'category_ids' => 'الفئات',
        'category_codes' => 'الفئات',
        'status' => 'الحالة',
        'notes' => 'الملاحظات',
        'external_refs' => 'معرّفات نظام تخطيط الموارد',
        'system' => 'النظام',
        'external_id' => 'المعرّف في نظام تخطيط الموارد',
        'description' => 'الوصف',
        'scopes' => 'الصلاحيات',
        'expires_in_days' => 'مدة الصلاحية بالأيام',
        'url' => 'الرابط',
        'event_types' => 'أنواع الأحداث',
        'file' => 'الملف',
        'type' => 'النوع',
        'mode' => 'الوضع',
        'format' => 'الصيغة',
        'competition_id' => 'المنافسة',
        'from' => 'من تاريخ',
        'to' => 'إلى تاريخ',
    ],

    'import' => [
        'row_errors' => [
            'required' => 'العمود :column مطلوب.',
            'invalid_format' => 'صيغة قيمة العمود :column غير صحيحة.',
            'unknown_region' => 'رمز المنطقة غير معروف.',
            'unknown_category' => 'رمز فئة واحد أو أكثر غير معروف.',
            'duplicate_in_file' => 'هذا المورد مكرر في الملف.',
            'vendor_email_taken' => 'يستخدم مورد آخر في دليلكم هذا البريد الإلكتروني.',
        ],
        'failures' => [
            'missing_columns' => 'يجب أن يحتوي الملف على الأعمدة: :columns.',
            'too_many_rows' => 'يحتوي الملف على أكثر من :max صف.',
            'empty_file' => 'لا يحتوي الملف على صف العناوين.',
            'unreadable' => 'تعذّرت قراءة الملف. استخدم القالب بصيغة CSV (UTF-8) أو XLSX.',
        ],
        'template' => [
            'columns' => [
                'external_system' => 'رمز نظام تخطيط الموارد، مثل sap_s4 أو oracle_fusion أو odoo أو custom:name. يُذكر مع external_id.',
                'external_id' => 'معرّف المورد في نظام تخطيط الموارد. مع external_system يحدد المورد عند إعادة الاستيراد.',
                'name' => 'الاسم النظامي أو التجاري.',
                'name_en' => 'الاسم بالإنجليزية.',
                'cr_number' => 'رقم السجل التجاري: 10 أرقام.',
                'vat_number' => 'الرقم الضريبي: 15 رقماً يبدأ وينتهي بالرقم 3.',
                'email' => 'بريد جهة الاتصال الذي تُرسل إليه الدعوات. لا يتكرر في دليلكم.',
                'contact_name' => 'اسم جهة الاتصال.',
                'phone' => 'جوال سعودي بالصيغة +9665XXXXXXXX.',
                'region_code' => 'رمز المنطقة من ورقة Lists، مثل RIY.',
                'city' => 'المدينة.',
                'category_codes' => 'رموز الفئات من ورقة Lists، مفصولة بالرمز ;',
                'status' => 'active أو blocked (لا يُدعى المورد المحظور). الفراغ يعني active.',
            ],
            'notes' => 'أبقِ صف العناوين كما هو. يُطابَق الصف بقيمتي external_system و external_id عند توفرهما، وإلا بالبريد الإلكتروني. تحقق أولاً ثم استورد الصفوف الصحيحة.',
        ],
    ],

    'exports' => [
        'failed' => 'تعذّر إنشاء ملف التصدير. حاول مرة أخرى.',
    ],

    'docs' => [
        'title' => 'واجهة بافو البرمجية العامة v1',
        'description' => 'مرجع واجهة بافو البرمجية للتكامل مع أنظمة تخطيط الموارد: بيانات اعتماد OAuth2 أو مفاتيح الواجهة، والموردون، والمنافسات، والدعوات، والعروض، والترسيات، والأحداث.',
    ],
];
