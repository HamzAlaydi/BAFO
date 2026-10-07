<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| وحدة المنصة (نموذج التواصل، المستندات القانونية، الملفات، الإعدادات)
|--------------------------------------------------------------------------
|
| المفاتيح نفسها الموجودة في lang/en/platform.php. رموز الأخطاء العامة في errors.php.
|
*/

return [

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الجوال',
        'company' => 'المنشأة',
        'subject' => 'الموضوع',
        'message' => 'الرسالة',
        'website_url' => 'الموقع الإلكتروني',
        'file' => 'الملف',
    ],

    'validation' => [
        'phone' => 'أدخل رقم جوال سعودياً بالصيغة ‎+9665XXXXXXXX.',
    ],

    'enums' => [
        'legal_document_code' => [
            'terms' => 'الشروط والأحكام',
            'privacy' => 'سياسة الخصوصية',
            'refund' => 'سياسة الاسترداد',
            'competition_rules' => 'قواعد المنافسات',
            'api_terms' => 'شروط استخدام واجهة البرمجة',
        ],
        'contact_status' => [
            'new' => 'جديدة',
            'read' => 'مقروءة',
            'archived' => 'مؤرشفة',
        ],
        'file_purpose' => [
            'organization_logo' => 'شعار المنشأة',
            'organization_profile' => 'ملف تعريف المنشأة',
            'user_avatar' => 'الصورة الشخصية',
            'competition_attachment' => 'مرفق المنافسة',
            'invoice_pdf' => 'الفاتورة',
            'competition_report' => 'تقرير المنافسة',
            'import_source' => 'ملف الاستيراد',
            'import_errors' => 'أخطاء الاستيراد',
            'export' => 'ملف التصدير',
        ],
        // الإعداد platform.release_scope (RELEASE_SCOPE.md §1.1).
        'release_scope' => [
            'core' => 'أساسي (المناقصات والمزايدات)',
            'full' => 'كامل (جميع المزايا)',
        ],
    ],

    'legal' => [
        'draft_notice' => 'مسودة: يقدّم المستشار القانوني النص النهائي',
        'placeholder_body' => 'نص مؤقت يُستبدل بالنص المعتمد من المستشار القانوني.',
    ],

];
