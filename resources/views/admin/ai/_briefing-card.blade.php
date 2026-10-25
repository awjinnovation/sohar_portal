{{-- Dashboard "Daily briefing" (contract §A5): loaded via JS so the dashboard never waits for the model; cached per tenant per day. --}}
@if(\App\Services\Ai\AiUsage::copilotAvailable($brand ?? null) && auth()->user()?->can('view payments') && \Illuminate\Support\Facades\Route::has('admin.ai.briefing'))
    <x-admin.card title="ai.admin.briefing" icon="stars" subtitle="ai.admin.briefing_hint" data-ai-briefing
        :data-ai-urls="json_encode(['show' => route('admin.ai.briefing'), 'regenerate' => route('admin.ai.briefing.regenerate')])"
        :data-ai-i18n="\App\Services\Ai\CopilotUi::i18n()">
        <x-slot:actions>
            <button type="button" class="btn btn-sm" data-ai-regenerate><i class="bi bi-arrow-repeat"></i>{{ __('ai.admin.regenerate') }}</button>
        </x-slot:actions>
        <div data-ai-body aria-live="polite">
            <div class="text-muted small">{{ __('ai.admin.briefing_loading') }}</div>
        </div>
    </x-admin.card>
    @include('admin.ai._assets')
@endif
