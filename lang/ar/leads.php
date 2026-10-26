<?php

return [
    'label' => 'طلب انضمام',
    'plural' => 'طلبات الانضمام',
    'unassigned' => 'غير مُسند',
    'no_notes' => 'لا توجد ملاحظات بعد. يُسجَّل هنا كل تغيير في الحالة تلقائيًا.',
    'whatsapp_greeting' => 'مرحبًا :name، شكرًا لاهتمامك بـ Drive-Thru Plus! ما الوقت المناسب لمكالمة قصيرة؟',

    'status' => [
        'new' => 'جديد',
        'contacted' => 'تم التواصل',
        'demo_booked' => 'عرض تجريبي محجوز',
        'won' => 'تم الكسب',
        'lost' => 'لم يكتمل',
    ],

    'source' => [
        'website' => 'الموقع',
        'manual' => 'أضافه الفريق',
        'referral' => 'ترشيح',
        'phone' => 'مكالمة',
        'event' => 'فعالية',
    ],

    'tabs' => [
        'open' => 'قيد المتابعة',
        'all' => 'الكل',
    ],

    'sections' => [
        'request' => 'الطلب',
        'pipeline' => 'مسار المبيعات',
        'notes' => 'الملاحظات والسجل',
    ],

    'fields' => [
        'services' => 'الخدمات',
        'restaurant_name' => 'المطعم',
        'contact_name' => 'الشخص المسؤول',
        'contact' => 'التواصل',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الجوال',
        'branches_count' => 'الفروع',
        'plan' => 'مهتم بـ',
        'message' => 'الرسالة',
        'source' => 'المصدر',
        'locale' => 'اللغة',
        'assignee' => 'المسؤول',
        'follow_up_at' => 'موعد المتابعة',
        'lost_reason' => 'سبب عدم الاكتمال',
        'note' => 'الملاحظة',
        'received' => 'تاريخ الورود',
        'contacted_at' => 'أول تواصل',
        'converted_to' => 'أصبح عميلًا',
    ],

    'filters' => [
        'mine' => 'المسندة إليّ',
        'follow_up_due' => 'متابعة مستحقة',
    ],

    'actions' => [
        'new' => 'طلب جديد',
        'change_status' => 'تحديث الحالة',
        'add_note' => 'إضافة ملاحظة',
        'assign_to_me' => 'إسناده إليّ',
        'whatsapp' => 'واتساب',
        'convert' => 'تحويل إلى عميل',
        'convert_heading' => 'فتح :restaurant كعميل',
        'convert_description' => 'راجع البيانات: يفتح هذا المطعم ومالكه والاشتراك في خطوة واحدة، ويُغلق الطلب كمكسوب.',
        'convert_submit' => 'فتح حساب العميل',
    ],

    'notifications' => [
        'status_updated' => 'تم تعيين الحالة إلى :status',
        'note_added' => 'تمت إضافة الملاحظة',
        'assigned' => 'تم إسناد الطلب إليك',
        'converted' => 'أصبح :restaurant عميلًا',
        'new' => [
            'title' => 'طلب جديد: :restaurant',
            'body' => ':contact · :branches فرع',
            'action' => 'فتح الطلب',
        ],
    ],

    'notes' => [
        'status_changed' => 'الحالة: :from ← :to',
        'converted' => 'تم التحويل إلى العميل :restaurant',
    ],

    'errors' => [
        'won_needs_conversion' => 'استخدم «تحويل إلى عميل» لكسب الطلب.',
        'already_won' => 'هذا الطلب أصبح عميلًا بالفعل.',
        'lost_needs_reason' => 'يرجى ذكر سبب عدم اكتمال الطلب.',
    ],

    'empty' => [
        'heading' => 'لا توجد طلبات هنا',
        'description' => 'تظهر هنا الطلبات الواردة من الموقع فورًا.',
    ],

    'mail' => [
        'ack' => [
            'subject' => 'وصلنا طلبك',
            'greeting' => 'مرحبًا :name،',
            'body' => 'شكرًا لاهتمامك بإطلاق :restaurant أونلاين مع Drive-Thru Plus.',
            'next' => 'سيتواصل معك أحد أعضاء فريقنا خلال يوم عمل واحد لترتيب عرض تجريبي قصير.',
        ],
    ],

    'form' => [
        'eyebrow' => 'احجز عرضًا',
        'title' => 'احجز عرضك المجاني.',
        'lead' => 'أخبرنا قليلًا عن مطعمك، وسنجهز كل شيء معك لتبدأ استقبال الطلبات خلال أيام.',
        'optional' => 'اختياري',
        'plan_help' => 'لم أقرر بعد',
        'message_placeholder' => 'هل هناك ما نحتاج معرفته؟ ساعات العمل، عدد المسارات، نظام نقاط البيع الحالي…',
        'privacy' => 'نستخدم هذه البيانات فقط للتواصل معك بخصوص Drive-Thru Plus.',
        'submit' => 'إرسال الطلب',
        'success_title' => 'شكرًا لك!',
        'success_text' => 'وصلنا طلبك، وسنتواصل معك خلال يوم عمل واحد.',
        'steps' => [
            'one' => ['title' => 'أرسل طلبك', 'text' => 'دقيقتان فقط، ودون أي التزام.'],
            'two' => ['title' => 'نجهزك بالكامل', 'text' => 'حسابك وفروعك وفريقك نعدّها معك.'],
            'three' => ['title' => 'ابدأ استقبال الطلبات', 'text' => 'ارفع المنيو واطبع رمز QR وانطلق.'],
        ],
        'attributes' => [
            'restaurant_name' => 'اسم المطعم',
            'contact_name' => 'اسمك',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الجوال',
            'branches_count' => 'عدد الفروع',
            'plan' => 'الباقة',
            'message' => 'الرسالة',
        ],
    ],
];
