<?php

declare(strict_types=1);

/*
| نصوص وحدة الفوترة (الباقات والاشتراكات والدفع والفواتير والرسوم المغطّاة).
| المفاتيح: billing.errors.<code>، billing.validation.*، billing.attributes.<field>،
| billing.enums.<enum_snake>.<value> (CONVENTIONS §6.2). المفاتيح نفسها في lang/en/billing.php.
*/

return [
    'errors' => [
        'plan_required' => 'تحتاج إلى باقة فعّالة أو تصريح مشاركة مغطّاة للانضمام.',
        'purchase_not_available_on_platform' => 'الشراء متاح عبر موقع بافو فقط.',
        'billing_profile_incomplete' => 'أكمل بيانات الفوترة لمنشأتك قبل الشراء.',
        'return_url_not_allowed' => 'عنوان العودة غير مسموح به.',
        'plan_not_available' => 'هذه الباقة غير متاحة.',
        'seats_out_of_range' => 'اختر عدد مستخدمين بين :min و:max.',
        'subscription_downgrade_not_allowed' => 'لا يمكن شراء باقة أقل أثناء سريان باقتك الحالية.',
        'subscription_renewal_too_early' => 'يمكنك التجديد في الأيام الأخيرة من باقتك الحالية.',
        'trial_not_available' => 'الفترة التجريبية غير متاحة لمنشأتك.',
        'coupon_invalid' => 'هذا الرمز غير صالح.',
        'coupon_expired' => 'هذا الرمز غير صالح في هذا الوقت.',
        'coupon_not_applicable' => 'هذا الرمز لا ينطبق على هذا الشراء.',
        'coupon_exhausted' => 'استُنفد هذا الرمز.',
        'invoice_pdf_not_ready' => 'ملف الفاتورة غير جاهز بعد.',
        'sponsorship_not_enabled' => 'خدمة الرسوم المغطّاة غير مفعّلة لمنشأتك.',
        'sponsorship_locked' => 'لا يمكن إجراء هذا التغيير بعد دفع قيمة التصاريح.',
        'sponsorship_payment_required' => 'ادفع أولاً قيمة :count من تصاريح المشاركة المغطّاة.',
        'sponsorship_already_funded' => 'لا يوجد ما يلزم دفعه. انشر المنافسة أو أرسل الدعوات مباشرة.',
        'invalid_webhook' => 'توقيع الإشعار غير صالح.',
        'gateway_error' => 'لم تستجب بوابة الدفع. حاول مرة أخرى.',
        'gateway_not_configured' => 'الدفع الإلكتروني غير متاح حالياً.',
        'invitation_cutoff_passed' => 'انتهى وقت دعوة المتنافسين.',
    ],

    'validation' => [
        'seats_required' => 'أدخل عدد المستخدمين.',
        'seats_positive' => 'يجب ألا يقل عدد المستخدمين عن 1.',
        'period_invalid' => 'يجب أن تكون النهاية بعد البداية.',
        'reason_required' => 'أدخل السبب.',
        'reference_required' => 'أدخل المرجع.',
        'billing_profile_field_missing' => 'حقل :attribute مطلوب للفوترة.',
        'item_codes' => [
            'invitation_duplicate' => 'هذه المنشأة مدعوّة مسبقاً.',
            'cannot_invite_own_organization' => 'لا يمكنك دعوة منشأتك.',
            'vendor_blocked' => 'هذا المورد محظور.',
            'vendor_not_found' => 'لم يُعثر على هذا المورد.',
            'not_found' => 'لم يُعثر على هذه المنشأة.',
        ],
    ],

    'attributes' => [
        'legal_name_ar' => 'الاسم النظامي بالعربية',
        'cr_number' => 'رقم السجل التجاري',
        'city' => 'المدينة',
        'address_building_number' => 'رقم المبنى',
        'address_street' => 'الشارع',
        'address_district' => 'الحي',
        'address_postal_code' => 'الرمز البريدي',
        'vat_number' => 'الرقم الضريبي',
        'plan_id' => 'الباقة',
        'interval' => 'مدة الاشتراك',
        'seats' => 'عدد المستخدمين',
        'coupon_code' => 'رمز القسيمة',
        'return_url' => 'عنوان العودة',
        'code' => 'الرمز',
        'purpose' => 'الغرض',
        'competition_id' => 'المنافسة',
        'mode' => 'الرسوم المغطّاة',
        'max_passes' => 'الحد الأقصى للتصاريح',
        'intent' => 'الإجراء',
        'invitations' => 'الدعوات',
        'invitations.email' => 'البريد الإلكتروني',
        'invitations.organization_id' => 'المنشأة',
        'invitations.vendor_id' => 'المورد',
        'invitations.name' => 'اسم جهة التواصل',
    ],

    'lines' => [
        'plan' => ':plan — :interval',
        'custom_seats' => ':plan — :seats مستخدمين — :interval',
        'sponsored_pass' => 'تصريح مشاركة مغطّاة — :reference',
    ],

    'gateway' => [
        'description' => 'طلب بافو :id',
    ],

    'voucher' => [
        'reason' => 'تصاريح غير مستخدمة :reference',
    ],

    'fake_pay' => [
        'title' => 'دفع تجريبي في بافو',
        'test_mode' => 'وضع تجريبي',
        'merchant' => 'أنت تدفع إلى بافو. لا تُنقل أي أموال حقيقية في هذه الصفحة.',
        'subtotal' => 'المجموع الفرعي',
        'credit' => 'رصيد من باقتك الحالية',
        'discount' => 'الخصم',
        'vat' => 'ضريبة القيمة المضافة :rate%',
        'total' => 'الإجمالي',
        'approve' => 'اعتماد الدفع',
        'decline' => 'رفض',
        'declined_message' => 'رُفضت عملية الدفع في الصفحة التجريبية.',
        'not_pending' => 'تمت معالجة هذه الدفعة مسبقاً (:status).',
        'hint' => 'الأسعار لا تشمل ضريبة القيمة المضافة، وتُضاف الضريبة أعلاه.',
    ],

    'invoice' => [
        'title' => 'فاتورة ضريبية',
        'number' => 'رقم الفاتورة',
        'issue_date' => 'تاريخ الإصدار',
        'supply_date' => 'تاريخ التوريد',
        'issued_at' => 'وقت الإصدار',
        'seller' => 'البائع',
        'buyer' => 'المشتري',
        'vat_number' => 'الرقم الضريبي',
        'cr_number' => 'رقم السجل التجاري',
        'description' => 'الوصف',
        'quantity' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'net' => 'المبلغ',
        'subtotal' => 'المجموع الفرعي',
        'discount' => 'الخصم',
        'taxable' => 'المبلغ الخاضع للضريبة',
        'vat' => 'ضريبة القيمة المضافة :rate%',
        'total' => 'الإجمالي شامل الضريبة',
    ],

    'enums' => [
        'entitlement_source' => [
            'plan' => 'الباقة',
            'sponsored_pass' => 'تصريح مشاركة مغطّاة',
            'grant' => 'منحة',
        ],
        'coupon_kind' => [
            'coupon' => 'قسيمة خصم',
            'voucher' => 'قسيمة رصيد',
        ],
        'discount_type' => [
            'percent' => 'نسبة مئوية',
            'fixed' => 'مبلغ ثابت',
        ],
        'coupon_scope' => [
            'any' => 'الكل',
            'subscription' => 'الاشتراكات',
            'sponsorship' => 'الرسوم المغطّاة',
        ],
        'payment_purpose' => [
            'subscription' => 'اشتراك',
            'sponsorship' => 'رسوم مغطّاة',
        ],
        'payment_status' => [
            'pending' => 'قيد الانتظار',
            'succeeded' => 'ناجحة',
            'failed' => 'فاشلة',
            'expired' => 'منتهية',
            'refunded' => 'مستردة',
        ],
        'payment_line_kind' => [
            'plan' => 'باقة',
            'custom_seats' => 'مقاعد مخصصة',
            'sponsored_pass' => 'تصريح مشاركة مغطّاة',
        ],
        'subscription_source' => [
            'paid' => 'مدفوع',
            'trial' => 'تجريبي',
            'grant' => 'منحة',
        ],
        'billing_interval' => [
            'monthly' => 'شهري',
            'annual' => 'سنوي',
        ],
        'subscription_status' => [
            'pending_payment' => 'بانتظار الدفع',
            'active' => 'نشط',
            'superseded' => 'مستبدل',
            'expired' => 'منتهي',
            'cancelled' => 'ملغى',
        ],
        'sponsorship_mode' => [
            'all' => 'جميع المدعوين',
            'selected' => 'مدعوون محددون',
        ],
        'sponsorship_status' => [
            'draft' => 'مسودة',
            'active' => 'نشطة',
            'settled' => 'تمت التسوية',
        ],
        'pass_source' => [
            'purchase' => 'شراء',
            'freed_slot' => 'مقعد محرر',
            'admin_grant' => 'منحة إدارية',
        ],
        'pass_status' => [
            'pending' => 'قيد الانتظار',
            'reserved' => 'محجوز',
            'joined' => 'مستخدم',
            'released' => 'محرر',
            'unused' => 'غير مستخدم',
            'void' => 'ملغى',
        ],
        'pass_release_reason' => [
            'declined' => 'اعتذر المدعو',
            'revoked' => 'أُلغيت الدعوة',
            'covered_by_own_plan' => 'مغطّى بباقة المدعو',
            'duplicate_organization' => 'منشأة مكررة',
        ],
        'invoice_type' => [
            'tax_invoice' => 'فاتورة ضريبية',
            'credit_note' => 'إشعار دائن',
        ],
        'e_invoice_status' => [
            'pending' => 'قيد الانتظار',
            'cleared' => 'معتمدة',
            'reported' => 'مُبلّغ عنها',
            'rejected' => 'مرفوضة',
            'failed' => 'فشلت',
        ],
        'coverage' => [
            'sponsored' => 'رسوم مغطّاة',
            'own_plan' => 'باقة المنشأة',
            'none' => 'غير مغطّاة',
        ],
        'access_state' => [
            'join_required' => 'انضم للمشاركة',
            'plan_required' => 'تلزم باقة',
            'full' => 'وصول كامل',
            'read_only' => 'اطلاع فقط',
            'unavailable' => 'غير متاح',
        ],
        'quote_line_reason' => [
            'not_selected' => 'غير محدد',
            'cap_reached' => 'بلغ الحد الأقصى للتصاريح',
            'own_plan' => 'مغطّى بباقة المدعو',
        ],
        'sponsorship_intent' => [
            'publish' => 'نشر',
            'invite' => 'دعوة',
        ],
        'gateway_payment_status' => [
            'paid' => 'مدفوعة',
            'failed' => 'فاشلة',
            'pending' => 'قيد الانتظار',
        ],
        'purchase_kind' => [
            'new' => 'اشتراك جديد',
            'renewal' => 'تجديد',
            'upgrade' => 'ترقية',
        ],
    ],
];
