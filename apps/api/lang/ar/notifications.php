<?php

declare(strict_types=1);

/*
| وحدة الإشعارات (ARCHITECTURE §11.4). المفاتيح والعناصر النائبة مُلزِمة، ويطابق هذا الملف
| نظيرَه الإنجليزي في المفاتيح. قالب كل نوع تحت مفتاح النوع بعد استبدال النقطة بشرطة سفلية.
|
| العناصر النائبة: :competition_type («مناقصة» أو «مزايدة»)، والأوقات بتوقيت الرياض
| (:close_time و:cutoff_time و:ends_at)، والمبالغ (:amount). السطور ذات الصيغ المتعددة تُعرض
| بـ trans_choice على العدد المذكور.
*/

return [

    // ---------------------------------------------------------------- catalogue (§11.3)

    'competition_invited' => [
        'title' => 'دعوة للمشاركة في :competition_type',
        'body' => 'تدعوك :issuer_name للمشاركة في «:competition_title».',
        'body_sponsored_suffix' => ' رسوم المشاركة مغطّاة.',
    ],

    'competition_updated' => [
        'title' => 'تحديث على المنافسة',
        'body' => 'حدّث طارح المنافسة تفاصيل «:competition_title».',
    ],

    'competition_opened' => [
        'title' => 'بدأ استقبال العروض',
        'body' => 'بدأ استقبال العروض في «:competition_title».',
    ],

    'competition_final_window_started' => [
        'title' => 'بدأت فترة التسعير النهائية',
        'body' => 'بدأت فترة التسعير النهائية في «:competition_title»، وتُغلق المنافسة عند :close_time.',
    ],

    'competition_closing_soon' => [
        'title' => 'المنافسة تُغلق قريباً',
        'body' => '{1} تُغلق «:competition_title» خلال دقيقة.|{2} تُغلق «:competition_title» خلال دقيقتين.|[3,10] تُغلق «:competition_title» خلال :minutes دقائق.|[11,*] تُغلق «:competition_title» خلال :minutes دقيقة.',
    ],

    'competition_extended' => [
        'title' => 'مُدّد وقت الإغلاق',
        'body' => 'مُدّد وقت إغلاق «:competition_title» إلى :close_time.',
    ],

    'competition_closed' => [
        'title' => 'أُغلقت المنافسة',
        'body' => 'أُغلق استقبال العروض في «:competition_title».',
        'mail_subject' => 'أُغلقت المنافسة: :competition_title',
        'mail_intro' => 'أُغلق استقبال العروض في «:competition_title». يمكنك الآن مراجعة العروض وتقييمها، ثم الترسية أو الإغلاق دون ترسية.',
        'mail_action' => 'مراجعة العروض',
    ],

    'competition_cancelled' => [
        'title' => 'أُلغيت المنافسة',
        'body' => 'أُلغيت «:competition_title». السبب: :reason',
        'mail_subject' => 'أُلغيت المنافسة: :competition_title',
        'mail_intro' => 'أُلغيت «:competition_title». السبب: :reason',
        'mail_action' => 'فتح المنافسة',
    ],

    'competition_not_awarded' => [
        'title' => 'أُغلقت المنافسة دون ترسية',
        'body' => 'أغلق طارح المنافسة «:competition_title» دون ترسية.',
        'mail_subject' => 'أُغلقت المنافسة دون ترسية: :competition_title',
        'mail_intro' => 'أغلق طارح المنافسة «:competition_title» دون ترسية. نشكركم على المشاركة.',
        'mail_action' => 'فتح المنافسة',
    ],

    'offer_received' => [
        'title' => 'عروض جديدة',
        'body' => 'وصلت عروض جديدة في «:competition_title».',
    ],

    'standing_lost_lead' => [
        'title' => 'لم يعد عرضك متصدراً',
        'body' => 'لم يعد عرضك العرض المتصدر في «:competition_title».',
    ],

    'bafo_invited' => [
        'title' => 'دعوة لجولة العرض النهائي',
        'body' => 'أنت مدعو لتقديم عرضك النهائي في «:competition_title» قبل :cutoff_time.',
        'mail_subject' => 'دعوة لجولة العرض النهائي: :competition_title',
        'mail_intro' => 'أنت مدعو لتقديم عرضك النهائي في «:competition_title» قبل :cutoff_time. يمكنك تقديم عرض واحد فقط في هذه الجولة.',
        'mail_action' => 'تقديم العرض النهائي',
    ],

    'bafo_ended' => [
        'title' => 'انتهت جولة العرض النهائي',
        'body' => 'انتهت جولة العرض النهائي في «:competition_title»، ويمكنك الآن الترسية.',
    ],

    'award_won' => [
        'title' => 'تمت الترسية عليكم',
        'body' => 'تمت ترسية «:competition_title» عليكم.',
        'mail_subject' => 'تمت الترسية عليكم: :competition_title',
        'mail_intro' => 'تمت ترسية «:competition_title» عليكم. تجدون التفاصيل في صفحة المنافسة.',
        'mail_action' => 'فتح المنافسة',
        'mail_message_label' => 'رسالة طارح المنافسة:',
    ],

    'award_not_selected' => [
        'title' => 'نتيجة المنافسة',
        'body' => 'تمت ترسية «:competition_title» على متنافس آخر. نشكركم على المشاركة.',
        'mail_subject' => 'نتيجة المنافسة: :competition_title',
        'mail_intro' => 'تمت ترسية «:competition_title» على متنافس آخر. نشكركم على المشاركة.',
        'mail_action' => 'فتح المنافسة',
    ],

    'award_revoked' => [
        'title' => 'أُلغيت الترسية',
        'body' => 'ألغى طارح المنافسة ترسية «:competition_title». السبب: :reason',
        'mail_subject' => 'أُلغيت الترسية: :competition_title',
        'mail_intro' => 'ألغى طارح المنافسة ترسية «:competition_title». السبب: :reason',
        'mail_action' => 'فتح المنافسة',
    ],

    'offer_voided' => [
        'title' => 'أُلغي عرض',
        'body' => 'ألغت إدارة المنصة أحد العروض في «:competition_title».',
        'mail_subject' => 'أُلغي عرض في :competition_title',
        'mail_intro' => 'ألغت إدارة المنصة أحد عروضكم في «:competition_title». يمكنكم مراجعة عروضكم في صفحة المنافسة.',
        'mail_action' => 'فتح المنافسة',
    ],

    'comment_created' => [
        'title' => 'رسالة جديدة في الاستفسارات',
        'body' => 'توجد رسالة جديدة في استفسارات «:competition_title».',
    ],

    'invitation_joined' => [
        'title' => 'انضم متنافس',
        'body' => 'انضمت :organization_name إلى «:competition_title».',
        'body_sponsored_suffix' => ' رسوم المشاركة مغطّاة.',
    ],

    'invitation_declined' => [
        'title' => 'اعتذار عن المشاركة',
        'body' => 'اعتذرت :organization_name عن المشاركة في «:competition_title».',
    ],

    'subscription_activated' => [
        'title' => 'تم تفعيل الاشتراك',
        'body' => 'تم تفعيل :plan_name حتى :ends_at.',
        'mail_subject' => 'تم تفعيل اشتراكك في بافو',
        'mail_intro' => 'تم تفعيل :plan_name حتى :ends_at.',
        'mail_action' => 'إدارة الاشتراك',
    ],

    'subscription_expiring' => [
        'title' => 'الاشتراك ينتهي قريباً',
        'body' => '{1} ينتهي اشتراكك غداً.|{2} ينتهي اشتراكك خلال يومين.|[3,10] ينتهي اشتراكك خلال :days_left أيام.|[11,*] ينتهي اشتراكك خلال :days_left يوماً.',
        'mail_subject' => 'اشتراكك ينتهي قريباً',
        'mail_intro' => 'ينتهي :plan_name في :ends_at. جدّد اشتراكك لمواصلة طرح المنافسات.',
        'mail_action' => 'تجديد الاشتراك',
    ],

    'subscription_expired' => [
        'title' => 'انتهى الاشتراك',
        'body' => 'انتهى اشتراك :plan_name.',
        'mail_subject' => 'انتهى اشتراكك في بافو',
        'mail_intro' => 'انتهى اشتراك :plan_name. جدّد اشتراكك لمواصلة طرح المنافسات.',
        'mail_action' => 'تجديد الاشتراك',
    ],

    'payment_failed' => [
        'title' => 'تعذّر إتمام الدفع',
        'body' => 'لم تكتمل عملية الدفع، ويمكنك المحاولة مرة أخرى.',
        'mail_subject' => 'تعذّر إتمام الدفع',
        'mail_intro' => 'لم تكتمل عملية الدفع، ويمكنك المحاولة مرة أخرى.',
        'mail_action' => 'الذهاب إلى الفوترة',
    ],

    'invoice_issued' => [
        'title' => 'فاتورة ضريبية جديدة',
        'body' => 'أُصدرت الفاتورة :number.',
        'mail_subject' => 'فاتورة ضريبية جديدة :number',
        'mail_intro' => 'أُصدرت الفاتورة :number.',
        'mail_action' => 'فتح الفاتورة',
        'mail_download_hint' => 'يمكنك تنزيل الفاتورة بصيغة PDF من صفحة الفوترة في لوحة التحكم.',
    ],

    'sponsorship_unused_passes' => [
        'title' => 'تصاريح مشاركة مغطّاة غير مستخدمة',
        'body' => 'عدد تصاريح المشاركة المغطّاة غير المستخدمة في «:competition_title»: :unused_count. ستصدر إدارة المنصة قسيمة بقيمتها.',
        'mail_subject' => 'تصاريح مشاركة مغطّاة غير مستخدمة: :competition_title',
        'mail_intro' => 'عدد تصاريح المشاركة المغطّاة غير المستخدمة في «:competition_title»: :unused_count. ستصدر إدارة المنصة قسيمة بقيمتها.',
        'mail_action' => 'فتح المنافسة',
    ],

    'sponsorship_publish_failed' => [
        'title' => 'تم الدفع ولم تُنشر المنافسة',
        'body' => 'استلمنا الدفع لكن تعذّر نشر «:competition_title». راجع التفاصيل ثم انشر مجدداً دون دفع إضافي.',
        'mail_subject' => 'تم الدفع ولم تُنشر المنافسة: :competition_title',
        'mail_intro' => 'استلمنا الدفع لكن تعذّر نشر «:competition_title». راجع التفاصيل ثم انشر مجدداً دون دفع إضافي.',
        'mail_action' => 'مراجعة المنافسة',
        'mail_reason' => 'السبب: :reason',
    ],

    'voucher_issued' => [
        'title' => 'قسيمة جديدة',
        'body' => 'أُضيفت القسيمة :code بقيمة :amount إلى حسابك.',
        'mail_subject' => 'قسيمة جديدة في حسابك',
        'mail_intro' => 'أُضيفت القسيمة :code بقيمة :amount إلى حسابك. يمكنك استخدامها عند الدفع.',
        'mail_action' => 'الذهاب إلى الفوترة',
    ],

    'webhook_endpoint_disabled' => [
        'title' => 'تعطّلت نقطة Webhook',
        'body' => 'عُطّلت نقطة Webhook :url بعد تكرار فشل الإرسال.',
        'mail_subject' => 'تعطّلت نقطة Webhook',
        'mail_intro' => 'عُطّلت نقطة Webhook :url بعد تكرار فشل الإرسال. راجع الرابط ثم أعد تفعيلها من صفحة التكاملات.',
        'mail_action' => 'إدارة التكاملات',
    ],

    'import_finished' => [
        'title' => 'اكتمل الاستيراد',
        'body' => 'اكتمل استيراد الموردين: :created جديد، :updated محدَّث، :errors خطأ.',
    ],

    'export_finished' => [
        'title' => 'ملف التصدير جاهز',
        'body' => 'ملف التصدير جاهز للتنزيل.',
    ],

    // ---------------------------------------------------------------- shared texts

    'reason_unspecified' => 'غير محدد',

    'mail' => [
        'brand' => 'بافو',
        'greeting' => 'مرحباً :name،',
        'salutation' => 'مع التحية،',
        'signature' => 'فريق بافو',
        'footer_reason' => 'تصلك هذه الرسالة لأن لديك حساباً في منصة بافو.',
        'rights' => 'جميع الحقوق محفوظة.',
    ],

    // Display time (CONVENTIONS §9.1): Gregorian months, Western digits, ص / م.
    'time' => [
        'months' => [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ],
        'am' => 'ص',
        'pm' => 'م',
        'separator' => '، ',
    ],

    'attributes' => [
        'unread' => 'غير المقروءة فقط',
        'page' => 'الصفحة',
        'per_page' => 'عدد العناصر في الصفحة',
        'token' => 'رمز الجهاز',
        'platform' => 'المنصة',
        'device_name' => 'اسم الجهاز',
        'app_version' => 'إصدار التطبيق',
    ],

    'validation' => [
        'semver' => 'يجب أن يكون :attribute رقم إصدار صحيحاً مثل 1.0.0.',
    ],

    'preview' => [
        'recipient_name' => 'سارة العتيبي',
    ],

    'enums' => [
        'device_platform' => [
            'ios' => 'iOS',
            'android' => 'Android',
            'web' => 'الويب',
        ],
        // Client labels (filters, settings), keyed by the catalogue type (§11.3).
        'notification_type' => [
            'competition' => [
                'invited' => 'دعوة لمنافسة',
                'updated' => 'تحديث منافسة',
                'opened' => 'بدء استقبال العروض',
                'final_window_started' => 'بدء فترة التسعير النهائية',
                'closing_soon' => 'قرب الإغلاق',
                'extended' => 'تمديد وقت الإغلاق',
                'closed' => 'إغلاق المنافسة',
                'cancelled' => 'إلغاء المنافسة',
                'not_awarded' => 'إغلاق دون ترسية',
            ],
            'offer' => [
                'received' => 'عروض جديدة',
                'voided' => 'إلغاء عرض',
            ],
            'standing' => [
                'lost_lead' => 'تغيّر العرض المتصدر',
            ],
            'bafo' => [
                'invited' => 'دعوة لجولة العرض النهائي',
                'ended' => 'انتهاء جولة العرض النهائي',
            ],
            'award' => [
                'won' => 'ترسية عليكم',
                'not_selected' => 'نتيجة المنافسة',
                'revoked' => 'إلغاء الترسية',
            ],
            'comment' => [
                'created' => 'رسالة في الاستفسارات',
            ],
            'invitation' => [
                'joined' => 'انضمام متنافس',
                'declined' => 'اعتذار عن المشاركة',
            ],
            'subscription' => [
                'activated' => 'تفعيل الاشتراك',
                'expiring' => 'قرب انتهاء الاشتراك',
                'expired' => 'انتهاء الاشتراك',
            ],
            'payment' => [
                'failed' => 'تعذّر الدفع',
            ],
            'invoice' => [
                'issued' => 'فاتورة ضريبية',
            ],
            'sponsorship' => [
                'unused_passes' => 'تصاريح مشاركة مغطّاة غير مستخدمة',
                'publish_failed' => 'تعذّر النشر بعد الدفع',
            ],
            'voucher' => [
                'issued' => 'قسيمة جديدة',
            ],
            'webhook' => [
                'endpoint_disabled' => 'تعطّل نقطة Webhook',
            ],
            'import' => [
                'finished' => 'اكتمال الاستيراد',
            ],
            'export' => [
                'finished' => 'ملف التصدير جاهز',
            ],
        ],
        'delivery_channel' => [
            'database' => 'داخل التطبيق',
            'push' => 'إشعار فوري',
            'mail' => 'البريد الإلكتروني',
        ],
    ],

];
