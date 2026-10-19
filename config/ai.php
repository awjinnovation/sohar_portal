<?php

/*
|--------------------------------------------------------------------------
| AI Experience limits (docs/ai_and_gate_contract.md §A1)
|--------------------------------------------------------------------------
| Provider settings (key, models, timeouts) live in config/services.php
| ('openai'). Per-tenant settings (assistant name, welcome, suggestions,
| monthly token budget, venue info) live in the tenant settings `ai` block
| (config/tenancy.php defaults).
*/

return [
    // Visitor concierge
    'chat' => [
        'max_message_chars' => 1000,
        'max_history_messages' => 12,       // previous messages (user + assistant) sent to the model
        'max_tool_rounds' => 4,
        'max_output_tokens' => 1200,
        'rate' => [
            'user' => ['max' => (int) env('AI_CHAT_RATE_USER', 20), 'minutes' => 10],
            'guest' => ['max' => (int) env('AI_CHAT_RATE_GUEST', 8), 'minutes' => 10],
            // Shared cap per IP for guests (festival Wi-Fi / carrier NAT share IPs).
            'guest_ip' => ['max' => (int) env('AI_CHAT_RATE_GUEST_IP', 60), 'minutes' => 10],
        ],
    ],

    'plan' => [
        'max_output_tokens' => 2500,
        'rate' => [
            'user' => ['max' => 6, 'minutes' => 10],
            'guest' => ['max' => 3, 'minutes' => 10],
            'guest_ip' => ['max' => 30, 'minutes' => 10],
        ],
    ],

    // Admin copilot + briefing + gate insights (per admin user)
    'copilot' => [
        'max_output_tokens' => 1500,
        'rate' => ['max' => 30, 'minutes' => 10],
        'regenerate_rate' => ['max' => 6, 'minutes' => 60],
    ],

    'conversation_ttl_days' => 30,
    'plan_ttl_days' => 90,

    // Approximate USD prices per 1M tokens, only for the cost estimate shown
    // in the README / admin (billing is OpenAI's). [input, output]
    'prices' => [
        'gpt-6-luna' => [0.10, 0.50],
        'gpt-6.1-sol' => [2.00, 10.00],
    ],
];
