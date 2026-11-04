<?php

// Visitor satisfaction survey «راضي» (docs/radi_satisfaction_contract.md §3).
return [
    'received' => 'Thank you, your feedback was received.',
    'errors' => [
        'already_answered' => 'You have already rated this visit today. One rating per event per day is accepted.',
        'device_required' => 'device_id is required when the visitor is not signed in.',
        'unknown_aspect' => 'Unknown rating aspect ":key".',
        'ticket_not_found' => 'This ticket is not in your account.',
        'event_not_found' => 'Event not found.',
    ],
];
