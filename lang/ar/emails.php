<?php

return [
    'invitation' => [
        'subject' => 'دعوة للانضمام إلى :store على Minivora',
        'body' => 'تمت دعوتك للمساعدة في إدارة :store بصفة :role.',
        'action' => 'قبول الدعوة',
        'expiry' => 'ينتهي هذا الرابط بعد 7 أيام.',
    ],
    'order_paid' => [
        'subject' => 'تم تأكيد الطلب رقم :number من :store',
        'body' => 'استلمنا الدفع للطلب رقم :number، و:store بيجهزه دلوقتي.',
    ],
    'order_shipped' => [
        'subject' => 'الطلب رقم :number من :store في الطريق',
        'body' => 'طلبك رقم :number خرج من :store ومع شركة الشحن.',
        'action' => 'تتبع طلبك',
    ],
    'order_cancelled' => [
        'subject' => 'تم إلغاء الطلب رقم :number من :store',
        'body' => 'تم إلغاء طلبك رقم :number من :store. رد على الإيميل ده لو ده حصل بالغلط.',
    ],
];
