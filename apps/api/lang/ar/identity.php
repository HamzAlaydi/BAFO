<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| وحدة الهوية (التسجيل، رمز التحقق، الدخول، الملف الشخصي، المنشأة، الفريق، حذف الحساب)
|--------------------------------------------------------------------------
|
| المفاتيح نفسها في lang/en/identity.php. رموز الأخطاء العامة في errors.php.
|
*/

return [

    'errors' => [
        'invalid_credentials' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
        'email_not_verified' => 'أكّد بريدك الإلكتروني للمتابعة. أرسلنا إليك رمز التحقق.',
        'account_inactive' => 'هذا الحساب غير نشط. تواصل مع مدير حساب منشأتك.',
        'organization_suspended' => 'حساب منشأتك موقوف. تواصل مع دعم بافو.',
        'otp_invalid' => 'الرمز غير صحيح.',
        'otp_expired' => 'انتهت صلاحية الرمز أو سبق استخدامه. اطلب رمزاً جديداً.',
        'otp_too_many_attempts' => 'أُبطل الرمز بعد محاولات كثيرة. اطلب رمزاً جديداً.',
        'otp_resend_cooldown' => 'انتظر :seconds ثانية قبل طلب رمز جديد.',
        'password_incorrect' => 'كلمة المرور الحالية غير صحيحة.',
        'team_invitation_invalid' => 'رابط الدعوة غير صالح أو منتهي. اطلب من مدير الحساب إعادة الإرسال.',
        'seat_limit_reached' => 'اكتملت مقاعد باقتكم. رقّوا الباقة أو أزيلوا عضواً أولاً.',
        'cannot_modify_owner' => 'لا يمكن تعديل مالك الحساب أو إزالته.',
        'cannot_modify_self' => 'لا يمكنك تعديل عضويتك أو إزالتها بنفسك.',
        'account_deletion_blocked' => 'لا يمكن حذف الحساب ما دامت لديه منافسات أو مشاركات قائمة.',
        'account_deletion_pending' => 'يوجد طلب حذف للحساب قيد الانتظار.',
        'invitation_email_mismatch' => 'يجب التسجيل بالبريد الإلكتروني الذي أُرسلت إليه الدعوة.',
    ],

    'validation' => [
        'email_taken' => 'يوجد حساب مسجل بهذا البريد الإلكتروني.',
        'cr_number_taken' => 'توجد منشأة مسجلة بهذا السجل التجاري.',
        'cr_number' => 'يجب أن يتكون رقم السجل التجاري من 10 أرقام.',
        'cr_number_immutable' => 'لا يمكن تعديل رقم السجل التجاري.',
        'phone' => 'أدخل رقم جوال سعودياً بالصيغة ‎+9665XXXXXXXX.',
        'vat_number' => 'يجب أن يتكون الرقم الضريبي من 15 رقماً يبدأ وينتهي بالرقم 3.',
        'vat_number_required' => 'أدخل الرقم الضريبي للمنشأة المسجلة في ضريبة القيمة المضافة.',
        'website' => 'أدخل عنوان موقع يبدأ بـ https://.',
        'four_digits' => 'يجب أن يتكون هذا الحقل من 4 أرقام.',
        'postal_code' => 'يجب أن يتكون الرمز البريدي من 5 أرقام.',
        'short_address' => 'يتكون العنوان المختصر من 4 أحرف إنجليزية كبيرة يليها 4 أرقام، مثل RRRD2929.',
        'invitation_token_invalid' => 'هذه الدعوة غير صالحة أو لم تعد متاحة.',
        'flag_not_held' => 'لا يمكنك منح صلاحية لا تملكها.',
    ],

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الجوال',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'locale' => 'اللغة',
        'code' => 'رمز التحقق',
        'purpose' => 'غرض الرمز',
        'device_name' => 'اسم الجهاز',
        'token' => 'رمز الدعوة',
        'invitation_token' => 'رمز الدعوة',
        'accept_terms' => 'الشروط والأحكام',
        'accept_privacy' => 'سياسة الخصوصية',
        'website_url' => 'الموقع الإلكتروني',
        'file' => 'الملف',
        'role' => 'الدور',
        'can_award' => 'صلاحية الترسية',
        'can_purchase' => 'صلاحية الشراء',
        'status' => 'الحالة',
        'reason' => 'السبب',
        'organization' => [
            'self' => 'المنشأة',
            'name' => 'اسم المنشأة',
            'cr_number' => 'رقم السجل التجاري',
            'region_id' => 'المنطقة',
            'city' => 'المدينة',
            'vat_registered' => 'التسجيل في ضريبة القيمة المضافة',
            'vat_number' => 'الرقم الضريبي',
            'legal_name_ar' => 'الاسم النظامي (بالعربية)',
            'legal_name_en' => 'الاسم النظامي (بالإنجليزية)',
            'website' => 'الموقع الإلكتروني',
            'category_ids' => 'الفئات',
            'visible_in_suggestions' => 'الظهور في اقتراحات طارحي المنافسات',
            'national_address' => [
                'self' => 'العنوان الوطني',
                'building_number' => 'رقم المبنى',
                'street' => 'الشارع',
                'district' => 'الحي',
                'postal_code' => 'الرمز البريدي',
                'additional_number' => 'الرقم الإضافي',
                'short_address' => 'العنوان المختصر',
            ],
        ],
    ],

    'deleted_user' => 'مستخدم محذوف',
    'deleted_organization' => 'منشأة محذوفة',

    'mail' => [
        'otp' => [
            'subject' => [
                'email_verification' => 'رمز التحقق من بافو',
                'password_reset' => 'رمز إعادة تعيين كلمة المرور في بافو',
                'invitation_claim' => 'رمز تأكيد الدعوة في بافو',
            ],
            'heading' => 'رمز التحقق',
            'intro' => [
                'email_verification' => 'استخدم هذا الرمز لتأكيد بريدك الإلكتروني في بافو.',
                'password_reset' => 'استخدم هذا الرمز لتعيين كلمة مرور جديدة لحسابك في بافو.',
                'invitation_claim' => 'استخدم هذا الرمز لتأكيد دعوة المنافسة المرسلة إلى هذا البريد.',
            ],
            'expires' => 'تنتهي صلاحية الرمز خلال :minutes دقائق، ويُستخدم مرة واحدة.',
            'ignore' => 'إن لم تطلب هذا الرمز فتجاهل هذه الرسالة.',
        ],
        'team_invitation' => [
            'subject' => 'دعوة للانضمام إلى :organization في بافو',
            'greeting' => 'مرحباً :name،',
            'intro' => 'دعاك :inviter للانضمام إلى :organization في بافو بدور :role.',
            'action' => 'قبول الدعوة',
            'expires' => 'الدعوة صالحة حتى :date.',
            'ignore' => 'إن لم تكن تتوقع هذه الدعوة فتجاهل هذه الرسالة.',
        ],
        'account_deletion' => [
            'subject' => 'جُدول حذف حسابك في بافو',
            'greeting' => 'مرحباً :name،',
            'intro' => [
                'user' => 'استلمنا طلبك لحذف حسابك في بافو، وسيُحذف في :date.',
                'organization' => 'استلمنا طلبك لحذف حساب منشأتك في بافو مع جميع مستخدميها، وسيُحذف في :date.',
            ],
            'cancel' => 'للاحتفاظ بحسابك، سجّل الدخول وألغِ الطلب قبل ذلك التاريخ.',
        ],
    ],

    'enums' => [
        'organization_status' => [
            'active' => 'نشطة',
            'suspended' => 'موقوفة',
            'deleted' => 'محذوفة',
        ],
        'user_status' => [
            'active' => 'نشط',
            'pending_verification' => 'بانتظار التحقق',
            'deleted' => 'محذوف',
        ],
        'org_role' => [
            'owner' => 'المالك',
            'admin' => 'مدير',
            'member' => 'عضو',
        ],
        'membership_status' => [
            'invited' => 'مدعو',
            'active' => 'نشط',
            'inactive' => 'غير نشط',
        ],
        'otp_purpose' => [
            'email_verification' => 'تأكيد البريد الإلكتروني',
            'password_reset' => 'إعادة تعيين كلمة المرور',
            'invitation_claim' => 'استلام الدعوة',
        ],
        'deletion_scope' => [
            'user' => 'حساب المستخدم',
            'organization' => 'حساب المنشأة',
        ],
        'deletion_status' => [
            'pending' => 'قيد الانتظار',
            'cancelled' => 'ملغى',
            'completed' => 'مكتمل',
        ],
        'permission' => [
            'organization' => [
                'update' => 'تعديل بيانات المنشأة',
            ],
            'team' => [
                'manage' => 'إدارة الفريق',
            ],
            'billing' => [
                'view' => 'الاطلاع على الفوترة',
                'purchase' => 'الشراء',
            ],
            'competitions' => [
                'create' => 'إنشاء المنافسات',
                'manage_all' => 'إدارة جميع المنافسات',
                'award' => 'الترسية',
            ],
            'participation' => [
                'submit_offers' => 'تقديم العروض',
            ],
            'integrations' => [
                'manage' => 'إدارة التكاملات',
            ],
            'account' => [
                'delete_organization' => 'حذف حساب المنشأة',
            ],
        ],
    ],

];
