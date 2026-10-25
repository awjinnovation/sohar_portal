<?php

namespace App\Services\Ai;

/** Strings for public/js/admin-ai.js (admin locale). */
class CopilotUi
{
    public static function i18n(): string
    {
        return json_encode([
            'working' => __('ai.admin.working'), 'inserted' => __('ai.admin.inserted'), 'undone' => __('ai.admin.undone'),
            'error' => __('ai.admin.error'), 'need_title' => __('ai.admin.need_title'), 'nothing_to_translate' => __('ai.admin.nothing_to_translate'),
            'nothing_to_shorten' => __('ai.admin.nothing_to_shorten'), 'brief_required' => __('ai.admin.brief_required'),
            'loading' => __('ai.admin.briefing_loading'), 'actions' => __('ai.admin.suggested_actions'), 'generated' => __('ai.admin.generated_at'),
        ], JSON_UNESCAPED_UNICODE);
    }
}
