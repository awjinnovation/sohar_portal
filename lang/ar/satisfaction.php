<?php

// Visitor satisfaction survey «راضي» (docs/radi_satisfaction_contract.md §3).
return [
    'received' => 'وصل تقييمك، شكرًا لك.',
    'errors' => [
        'already_answered' => 'قيّمت هذه الزيارة اليوم، ويُقبل تقييم واحد لكل فعالية في اليوم.',
        'device_required' => 'معرّف الجهاز مطلوب حين لا يكون الزائر مسجّل الدخول.',
        'unknown_aspect' => 'جانب التقييم «:key» غير معروف.',
        'ticket_not_found' => 'التذكرة غير موجودة في حسابك.',
        'event_not_found' => 'الفعالية غير موجودة.',
    ],
];
