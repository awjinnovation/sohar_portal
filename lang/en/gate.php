<?php

// Gate scanner API messages (App\Services\Gate\ScanResult). Keep in sync with lang/ar/gate.php.
return [
    'results' => [
        'valid' => 'Welcome — entry granted',
        'exit' => 'Exit recorded',
        'reentry_ok' => 'Re-entry allowed',
        'already_used' => 'Ticket already used',
        'invalid' => 'Invalid ticket',
        'wrong_event' => 'This ticket is for another event',
        'wrong_date' => 'This ticket is not valid today',
        'not_yet_open' => 'Gates are not open yet',
        'cancelled' => 'Ticket cancelled',
        'refunded' => 'Ticket refunded',
        'wrong_gate' => 'Wrong gate — please use another gate',
    ],
    'details' => [
        'used_at' => 'used at :time (:gate)',
        'used_at_no_gate' => 'used at :time',
        'valid_on' => 'valid on :date',
        'expired' => 'the ticket has expired',
        'event_ended' => 'the event has ended',
        'opens_at' => 'doors open at :time',
        'gate_types' => 'this gate accepts: :types',
    ],
    'people' => '{1} 1 person|[2,*] :count people',
    'errors' => [
        'pairing_code_invalid' => 'This pairing code is not valid.',
        'pairing_code_expired' => 'This pairing code has expired. Ask an admin for a new one.',
        'pairing_code_used' => 'This pairing code was already used.',
        'device_revoked' => 'This device was revoked. Pair it again.',
        'unauthenticated' => 'Device not paired or token invalid.',
        'tenant_mismatch' => 'This device belongs to another organizer.',
        'gate_inactive' => 'This gate is closed.',
        'no_gate' => 'This device is not assigned to a gate.',
        'not_staff' => 'This account is not gate staff for this organizer.',
    ],
];
