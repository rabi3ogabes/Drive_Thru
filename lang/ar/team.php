<?php

return [
    'label' => 'عضو الفريق',
    'plural' => 'فريق المنصة',
    'navigation' => 'الفريق',
    'super_admin' => 'المسؤول الأعلى',

    'sections' => [
        'person' => 'البيانات الشخصية',
    ],

    'fields' => [
        'name' => 'الاسم الكامل',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الجوال',
        'locale' => 'اللغة',
        'role' => 'الدور',
        'two_factor' => 'التحقق الثنائي',
        'last_seen' => 'آخر ظهور',
    ],

    'status' => [
        'active' => 'نشط',
        'invited' => 'مدعو',
        'suspended' => 'موقوف',
    ],

    'actions' => [
        'invite' => 'دعوة عضو',
        'unlock' => 'فتح تسجيل الدخول',
        'unlock_description' => 'يسمح لهذا الشخص بمحاولة تسجيل الدخول فورًا.',
        'reset_two_factor' => 'إعادة ضبط التحقق الثنائي',
        'reset_two_factor_description' => 'عند فقدان الهاتف: سيضبط تطبيق مصادقة جديدًا عند دخوله التالي.',
        'suspend' => 'إيقاف الوصول',
        'suspend_description' => 'يُسجَّل خروجه من كل مكان فورًا ولا يستطيع الدخول حتى إعادة التفعيل.',
        'reactivate' => 'إعادة تفعيل الوصول',
    ],

    'notifications' => [
        'unlocked' => 'تم فتح تسجيل الدخول',
        'not_locked' => 'هذا الحساب لم يكن مقفلًا',
        'two_factor_reset' => 'تمت إعادة ضبط التحقق الثنائي',
        'suspended' => 'لم يعد بإمكان :name تسجيل الدخول',
        'reactivated' => 'أصبح بإمكان :name تسجيل الدخول مجددًا',
    ],

    'roles' => [
        'label' => 'دور',
        'plural' => 'الأدوار',
        'system' => 'أساسي',
        'empty' => 'لا توجد أدوار بعد',
        'permissions_count' => '{1} صلاحية واحدة|{2} صلاحيتان|[3,10] :count صلاحيات|[11,*] :count صلاحية',
        'sections' => [
            'identity' => 'الدور',
            'permissions' => 'ما يمكن لهذا الدور فعله',
            'permissions_hint' => 'مقسمة حسب المجال. يملك المسؤول الأعلى كل الصلاحيات دائمًا.',
        ],
        'fields' => [
            'name' => 'الاسم',
            'description' => 'الوصف',
            'permissions' => 'الصلاحيات',
            'members' => 'الأعضاء',
        ],
    ],
];
