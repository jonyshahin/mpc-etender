<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Arabic)
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'حقل :attribute يجب أن يكون رابطًا صالحًا.',
    'after' => 'يجب أن يكون حقل :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على أحرف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام فقط.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'حقل :attribute يجب أن يكون مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على أحرف ورموز إنجليزية فقط.',
    'before' => 'يجب أن يكون حقل :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا قبل أو يساوي :date.',
    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم ملف :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute بين :min و :max.',
        'string' => 'يجب أن يحتوي حقل :attribute على عدد أحرف بين :min و :max.',
    ],
    'boolean' => 'يجب أن يكون حقل :attribute إما صحيح أو خطأ.',
    'can' => 'حقل :attribute يحتوي على قيمة غير مسموح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير مطابق.',
    'contains' => 'حقل :attribute لا يحتوي على قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'حقل :attribute يجب أن يكون تاريخًا صالحًا.',
    'date_equals' => 'يجب أن يكون حقل :attribute تاريخًا مساويًا لـ :date.',
    'date_format' => 'يجب أن يطابق حقل :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal منزلة عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يختلف حقل :attribute عن :other.',
    'digits' => 'يجب أن يحتوي حقل :attribute على :digits رقمًا.',
    'digits_between' => 'يجب أن يحتوي حقل :attribute على عدد أرقام بين :min و :max.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريدًا إلكترونيًا صالحًا.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'exists' => 'القيمة المحددة في حقل :attribute غير موجودة.',
    'extensions' => 'يجب أن يكون امتداد حقل :attribute أحد التالي: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفًا.',
    'filled' => 'حقل :attribute مطلوب.',
    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من :value.',
        'string' => 'يجب أن يحتوي حقل :attribute على أكثر من :value حرف.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصر على الأقل.',
        'file' => 'يجب أن يكون حجم ملف :attribute :value كيلوبايت على الأقل.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :value أو أكبر.',
        'string' => 'يجب أن يحتوي حقل :attribute على :value حرف على الأقل.',
    ],
    'hex_color' => 'يجب أن يكون حقل :attribute لون HEX صالح.',
    'image' => 'يجب أن يكون حقل :attribute صورة.',
    'in' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'in_array' => 'حقل :attribute غير موجود في :other.',
    'in_array_keys' => 'يجب أن يحتوي حقل :attribute على مفتاح واحد على الأقل من: :values.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صالحًا.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صالحًا.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صالحًا.',
    'json' => 'يجب أن يكون حقل :attribute نص JSON صالح.',
    'list' => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون حقل :attribute بأحرف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من :value.',
        'string' => 'يجب أن يحتوي حقل :attribute على أقل من :value حرف.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب ألا يتجاوز حجم ملف :attribute :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :value أو أقل.',
        'string' => 'يجب ألا يتجاوز حقل :attribute :value حرف.',
    ],
    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صالحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يتجاوز حجم ملف :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة حقل :attribute :max.',
        'string' => 'يجب ألا يتجاوز حقل :attribute :max حرف.',
    ],
    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقم.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عنصر على الأقل.',
        'file' => 'يجب أن يكون حجم ملف :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :min على الأقل.',
        'string' => 'يجب أن يحتوي حقل :attribute على :min حرف على الأقل.',
    ],
    'min_digits' => 'يجب أن يحتوي حقل :attribute على :min رقم على الأقل.',
    'missing' => 'يجب أن يكون حقل :attribute مفقودًا.',
    'missing_if' => 'يجب أن يكون حقل :attribute مفقودًا عندما يكون :other هو :value.',
    'missing_unless' => 'يجب أن يكون حقل :attribute مفقودًا ما لم يكن :other هو :value.',
    'missing_with' => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون حقل :attribute مفقودًا عند وجود جميع :values.',
    'multiple_of' => 'يجب أن تكون قيمة حقل :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'not_regex' => 'صيغة حقل :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة حقل :attribute في تسريب بيانات. الرجاء اختيار قيمة مختلفة.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجودًا.',
    'present_if' => 'يجب أن يكون حقل :attribute موجودًا عندما يكون :other هو :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجودًا ما لم يكن :other هو :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجودًا عند وجود جميع :values.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما يكون :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عند قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute محظور عند رفض :other.',
    'prohibited_unless' => 'حقل :attribute محظور ما لم يكن :other في :values.',
    'prohibits' => 'حقل :attribute يحظر وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مداخل للقيم: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم يكن :other في :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود جميع :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود جميع :values.',
    'same' => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :size.',
        'string' => 'يجب أن يحتوي حقل :attribute على :size حرف.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string' => 'حقل :attribute يجب أن يكون نصًا.',
    'timezone' => 'يجب أن يكون حقل :attribute منطقة زمنية صالحة.',
    'unique' => 'قيمة حقل :attribute مستخدمة من قبل.',
    'uploaded' => 'فشل تحميل حقل :attribute.',
    'uppercase' => 'يجب أن يكون حقل :attribute بأحرف كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطًا صالحًا.',
    'ulid' => 'يجب أن يكون حقل :attribute ULID صالحًا.',
    'uuid' => 'يجب أن يكون حقل :attribute UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify per-attribute / per-rule overrides. B.1b will land
    | domain-specific messages (e.g. opening-after-submission business copy)
    | under this key.
    |
    */

    'custom' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes (Arabic)
    |--------------------------------------------------------------------------
    |
    | Mirrored from lang/en/validation.php. Conceptual plurals used for
    | *_ids fields ("الفئات" not "معرّفات الفئات"). Parenthetical language
    | suffixes mirror the EN shape — "العنوان (بالإنجليزية)" not bare "العنوان".
    | Verb-phrase concepts translated as actions, not word-for-word.
    |
    */

    'attributes' => [
        // ── Tender ─────────────────────────────────────────────
        'tender_type' => 'نوع المناقصة',
        'submission_deadline' => 'الموعد النهائي للتقديم',
        'opening_date' => 'تاريخ الفتح',
        'estimated_value' => 'القيمة التقديرية',
        'currency' => 'العملة',
        'is_two_envelope' => 'نظام الظرفين',
        'technical_pass_score' => 'درجة النجاح الفني',
        'requires_site_visit' => 'زيارة الموقع مطلوبة',
        'site_visit_date' => 'تاريخ زيارة الموقع',
        'extends_deadline' => 'تمديد الموعد النهائي للتقديم',
        'new_deadline' => 'الموعد النهائي الجديد',
        'project_id' => 'المشروع',
        'project_ids' => 'المشاريع',

        // ── BOQ + criteria ─────────────────────────────────────
        'boq_sections' => 'أقسام جدول الكميات',
        'boq_prices' => 'أسعار جدول الكميات',
        'boq_item_id' => 'بند جدول الكميات',
        'evaluation_criteria' => 'معايير التقييم',
        'criterion_id' => 'معيار التقييم',
        'item_code' => 'رمز البند',
        'items' => 'البنود',
        'unit' => 'الوحدة',
        'unit_price' => 'سعر الوحدة',
        'total_price' => 'السعر الإجمالي',
        'quantity' => 'الكمية',
        'envelope' => 'المظروف',
        'weight_percentage' => 'نسبة الوزن',
        'max_score' => 'الدرجة القصوى',
        'score' => 'الدرجة',
        'sort_order' => 'ترتيب العرض',

        // ── Documents ──────────────────────────────────────────
        'documents' => 'المستندات',
        'document_type' => 'نوع المستند',
        'doc_type' => 'نوع المستند',
        'file' => 'الملف',
        'issue_date' => 'تاريخ الإصدار',
        'expiry_date' => 'تاريخ الانتهاء',

        // ── Vendor ─────────────────────────────────────────────
        'company_name' => 'اسم الشركة',
        'company_name_ar' => 'اسم الشركة (بالعربية)',
        'trade_license_no' => 'رقم السجل التجاري',
        'contact_person' => 'الشخص المسؤول',
        'whatsapp_number' => 'رقم الواتساب',
        'website' => 'الموقع الإلكتروني',
        'category_ids' => 'الفئات',

        // ── User / auth ────────────────────────────────────────
        'name' => 'الاسم',
        'name_en' => 'الاسم (بالإنجليزية)',
        'name_ar' => 'الاسم (بالعربية)',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'role' => 'الدور',
        'role_id' => 'الدور',
        'permission_ids' => 'الصلاحيات',
        'project_role' => 'الدور في المشروع',
        'language_pref' => 'تفضيل اللغة',
        'language' => 'اللغة',
        'locale' => 'اللغة',
        'must_change_password' => 'تغيير كلمة المرور مطلوب',

        // ── Project / category ─────────────────────────────────
        'code' => 'رمز المشروع',
        'slug' => 'المعرّف',
        'parent_id' => 'الفئة الأب',
        'client_name' => 'اسم العميل',
        'location' => 'الموقع',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',

        // ── Bilingual content ─────────────────────────────────
        'title' => 'العنوان',
        'title_en' => 'العنوان (بالإنجليزية)',
        'title_ar' => 'العنوان (بالعربية)',
        'subject' => 'الموضوع',
        'description' => 'الوصف',
        'description_en' => 'الوصف (بالإنجليزية)',
        'description_ar' => 'الوصف (بالعربية)',
        'content_en' => 'المحتوى (بالإنجليزية)',
        'content_ar' => 'المحتوى (بالعربية)',
        'justification' => 'المبرر',

        // ── Address ────────────────────────────────────────────
        'address' => 'العنوان',
        'city' => 'المدينة',
        'country' => 'الدولة',

        // ── Common ─────────────────────────────────────────────
        'status' => 'الحالة',
        'reason' => 'السبب',
        'notes' => 'الملاحظات',
        'comments' => 'التعليقات',
        'message' => 'الرسالة',
        'question' => 'السؤال',
        'answer' => 'الإجابة',
        'type' => 'النوع',
        'count' => 'العدد',
        'is_active' => 'مفعّل',
        'complete' => 'الإكمال',

        // ── Evaluation / approval ──────────────────────────────
        'committee_type' => 'نوع اللجنة',
        'authorizer_id' => 'المخوّل',
        'delegatee_id' => 'المفوَّض إليه',
        'scores' => 'الدرجات',
        'technical_notes' => 'الملاحظات الفنية',

        // ── Settings (Admin Key/Value table) ───────────────────
        'settings' => 'الإعدادات',
        'key' => 'المفتاح',
        'value' => 'القيمة',
    ],
];
