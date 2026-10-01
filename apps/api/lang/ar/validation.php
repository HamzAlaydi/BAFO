<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق من صحة البيانات
    |--------------------------------------------------------------------------
    |
    | الرسائل الافتراضية لقواعد التحقق في Laravel. أسماء الحقول تُستبدل
    | عبر مصفوفة "attributes" في أسفل الملف.
    |
    */

    'accepted' => 'يجب قبول :attribute.',
    'accepted_if' => 'يجب قبول :attribute عندما يكون :other بقيمة :value.',
    'active_url' => 'يجب أن يكون :attribute رابطاً صحيحاً.',
    'after' => 'يجب أن يكون :attribute تاريخاً لاحقاً لـ :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً لاحقاً لـ :date أو مساوياً له.',
    'alpha' => 'يجب أن يحتوي :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'any_of' => 'قيمة :attribute غير صالحة.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'array_keys' => 'يجب ألا يحتوي :attribute إلا على المفاتيح التالية: :values.',
    'ascii' => 'يجب أن يحتوي :attribute على حروف وأرقام ورموز أحادية البايت فقط.',
    'base64' => 'يجب أن يكون :attribute نصاً صالحاً بترميز Base64.',
    'before' => 'يجب أن يكون :attribute تاريخاً سابقاً لـ :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخاً سابقاً لـ :date أو مساوياً له.',
    'between' => [
        'array' => 'يجب أن يحتوي :attribute على عدد من العناصر بين :min و:max.',
        'file' => 'يجب أن يكون حجم :attribute بين :min و:max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و:max.',
        'string' => 'يجب أن يكون طول :attribute بين :min و:max حرفاً.',
    ],
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحاً أو خطأ.',
    'can' => 'يحتوي :attribute على قيمة غير مصرّح بها.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'contains' => 'يفتقد :attribute إلى قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون :attribute تاريخاً صحيحاً.',
    'date_equals' => 'يجب أن يكون :attribute تاريخاً مساوياً لـ :date.',
    'date_format' => 'يجب أن يطابق :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي :attribute على :decimal منازل عشرية.',
    'declined' => 'يجب رفض :attribute.',
    'declined_if' => 'يجب رفض :attribute عندما يكون :other بقيمة :value.',
    'different' => 'يجب أن يكون :attribute و:other مختلفين.',
    'digits' => 'يجب أن يتكوّن :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يتكوّن :attribute من عدد أرقام بين :min و:max.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'يحتوي :attribute على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي :attribute على أيٍّ مما يلي: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي :attribute بأيٍّ مما يلي: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ :attribute بأيٍّ مما يلي: :values.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'encoding' => 'يجب أن يكون :attribute بترميز :encoding.',
    'ends_with' => 'يجب أن ينتهي :attribute بأحد ما يلي: :values.',
    'enum' => 'القيمة المختارة في :attribute غير صالحة.',
    'exists' => 'القيمة المختارة في :attribute غير صالحة.',
    'extensions' => 'يجب أن يكون امتداد :attribute أحد ما يلي: :values.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'filled' => 'يجب أن يحتوي :attribute على قيمة.',
    'gt' => [
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string' => 'يجب أن يكون طول :attribute أكثر من :value حرفاً.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي :attribute على :value عنصر أو أكثر.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت أو مساوياً له.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value أو مساوية لها.',
        'string' => 'يجب أن يكون طول :attribute :value حرفاً أو أكثر.',
    ],
    'hex_color' => 'يجب أن يكون :attribute لوناً صحيحاً بالصيغة الست عشرية.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'القيمة المختارة في :attribute غير صالحة.',
    'in_array' => 'يجب أن تكون قيمة :attribute موجودة في :other.',
    'in_array_keys' => 'يجب أن يحتوي :attribute على مفتاح واحد على الأقل مما يلي: :values.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صحيحاً.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صحيحاً.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صحيحاً.',
    'json' => 'يجب أن يكون :attribute نصاً صحيحاً بصيغة JSON.',
    'list' => 'يجب أن يكون :attribute قائمة.',
    'lowercase' => 'يجب أن يكون :attribute بأحرف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي :attribute على أقل من :value عنصر.',
        'file' => 'يجب أن يكون حجم :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
        'string' => 'يجب أن يكون طول :attribute أقل من :value حرفاً.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم :attribute أقل من :value كيلوبايت أو مساوياً له.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value أو مساوية لها.',
        'string' => 'يجب ألا يتجاوز طول :attribute :value حرفاً.',
    ],
    'mac_address' => 'يجب أن يكون :attribute عنوان MAC صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'string' => 'يجب ألا يتجاوز طول :attribute :max حرفاً.',
    ],
    'max_digits' => 'يجب ألا يحتوي :attribute على أكثر من :max أرقام.',
    'mimes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min حرفاً.',
    ],
    'min_digits' => 'يجب أن يحتوي :attribute على :min أرقام على الأقل.',
    'missing' => 'يجب ألا يُرسَل :attribute.',
    'missing_if' => 'يجب ألا يُرسَل :attribute عندما يكون :other بقيمة :value.',
    'missing_unless' => 'يجب ألا يُرسَل :attribute ما لم يكن :other بقيمة :value.',
    'missing_with' => 'يجب ألا يُرسَل :attribute عند وجود :values.',
    'missing_with_all' => 'يجب ألا يُرسَل :attribute عند وجود :values.',
    'multiple_of' => 'يجب أن تكون قيمة :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المختارة في :attribute غير صالحة.',
    'not_regex' => 'صيغة :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهر :attribute المُدخل في تسريب بيانات. يُرجى اختيار :attribute مختلف.',
    ],
    'present' => 'يجب إرسال :attribute.',
    'present_if' => 'يجب إرسال :attribute عندما يكون :other بقيمة :value.',
    'present_unless' => 'يجب إرسال :attribute ما لم يكن :other بقيمة :value.',
    'present_with' => 'يجب إرسال :attribute عند وجود :values.',
    'present_with_all' => 'يجب إرسال :attribute عند وجود :values.',
    'prohibited' => 'لا يُسمح بإرسال :attribute.',
    'prohibited_if' => 'لا يُسمح بإرسال :attribute عندما يكون :other بقيمة :value.',
    'prohibited_if_accepted' => 'لا يُسمح بإرسال :attribute عند قبول :other.',
    'prohibited_if_declined' => 'لا يُسمح بإرسال :attribute عند رفض :other.',
    'prohibited_unless' => 'لا يُسمح بإرسال :attribute ما لم تكن قيمة :other ضمن :values.',
    'prohibits' => 'وجود :attribute يمنع إرسال :other.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي :attribute على مدخلات لكلٍّ من: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other بقيمة :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم تكن قيمة :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أيٍّ من :values.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يكون طول :attribute :size حرفاً.',
    ],
    'starts_with' => 'يجب أن يبدأ :attribute بأحد ما يلي: :values.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صحيحة.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'تعذّر رفع :attribute.',
    'uppercase' => 'يجب أن يكون :attribute بأحرف كبيرة.',
    'url' => 'يجب أن يكون :attribute رابطاً صحيحاً.',
    'ulid' => 'يجب أن يكون :attribute معرّف ULID صحيحاً.',
    'uuid' => 'يجب أن يكون :attribute معرّف UUID صحيحاً.',

    /*
    |--------------------------------------------------------------------------
    | رسائل مخصّصة
    |--------------------------------------------------------------------------
    |
    | رسائل خاصة بحقل وقاعدة محددين، بصيغة "attribute.rule".
    |
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول
    |--------------------------------------------------------------------------
    |
    | تُستبدل بها أسماء الحقول البرمجية في الرسائل (مثلاً "email" ← "البريد الإلكتروني").
    | تُضيف الوحدات أسماء حقولها هنا بالعربية والإنجليزية معاً.
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الجوال',
        'mobile' => 'رقم الجوال',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'otp' => 'رمز التحقق',
        'code' => 'الرمز',
        'token' => 'الرمز',
        'locale' => 'اللغة',
        'title' => 'العنوان',
        'description' => 'الوصف',
        'notes' => 'الملاحظات',
        'message' => 'الرسالة',
        'file' => 'الملف',
        'files' => 'الملفات',
        'attachments' => 'المرفقات',
        'city' => 'المدينة',
        'region' => 'المنطقة',
        'address' => 'العنوان',
        'national_address' => 'العنوان الوطني',
        'postal_code' => 'الرمز البريدي',
        'organization' => 'المنشأة',
        'organization_name' => 'اسم المنشأة',
        'cr_number' => 'رقم السجل التجاري',
        'vat_number' => 'الرقم الضريبي',
        'category' => 'الفئة',
        'category_id' => 'الفئة',
        'competition' => 'المنافسة',
        'competition_id' => 'المنافسة',
        'direction' => 'نوع المنافسة',
        'starts_at' => 'تاريخ البدء',
        'ends_at' => 'تاريخ الانتهاء',
        'amount' => 'المبلغ',
        'amount_minor' => 'المبلغ',
        'currency' => 'العملة',
        'quantity' => 'الكمية',
        'offer' => 'العرض',
        'coupon' => 'القسيمة',
        'coupon_code' => 'رمز القسيمة',
        'plan' => 'الباقة',
        'plan_id' => 'الباقة',
        'reason' => 'السبب',
        'justification' => 'المبرر',
    ],

];
