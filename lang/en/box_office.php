<?php

// Gate box office (App\Services\Gate\BoxOfficeService). Keep in sync with lang/ar/box_office.php.
return [
    'errors' => [
        'sales_disabled' => 'Selling tickets at the gates is not enabled for this organizer.',
        'gate_cannot_sell' => 'This gate does not sell tickets. Ask an admin to enable selling for it.',
        'no_gate' => 'This device is not assigned to a gate.',
        'staff_login_required' => 'Sign in as gate staff first to sell tickets.',
        'staff_cannot_sell' => 'This account may not sell tickets. Sign in with an account that can.',
        'event_not_sellable' => 'This event is not on sale at this gate.',
        'type_not_sellable' => 'This ticket type is not on sale at this gate.',
        'date_not_allowed' => 'The gate sells tickets for today only.',
        'sold_out' => 'Not enough tickets are left for this day.',
        'invalid_phone' => 'This phone number is not valid. Enter a valid mobile number.',
        'order_expired' => 'The payment link of this order has expired. Create a new order.',
        'order_not_pending' => 'This order is no longer awaiting payment.',
        'order_not_paid' => 'This order is not paid yet.',
        'sms_limit' => 'This order has reached the maximum number of text messages.',
        'sms_wait' => 'Wait :seconds before sending the message again.',
        'tickets_not_in_order' => 'Some of the selected tickets do not belong to this order.',
    ],
    'tickets' => '{1} 1 ticket|[2,*] :count tickets',
    'minutes' => '{1} 1 minute|[2,*] :count minutes',
    'seconds' => '{1} 1 second|[2,*] :count seconds',
    'types' => [
        'standard' => 'Standard',
        'vip' => 'VIP',
        'premium' => 'Premium',
        'early_bird' => 'Early bird',
        'family' => 'Family',
        'child' => 'Child',
        'adult' => 'Adult',
    ],
    'statuses' => [
        'pending' => 'Awaiting payment',
        'paid' => 'Paid',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'paid_unfulfilled' => 'Refund due',
        'refunded' => 'Refunded',
    ],
];
