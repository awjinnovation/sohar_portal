{{-- AI copilot for the announcement create/edit form (contract §A5): draft from a one-line brief, translate, shorten. --}}
@if(\App\Services\Ai\AiUsage::copilotAvailable($brand ?? null) && auth()->user()?->can('manage announcements') && \Illuminate\Support\Facades\Route::has('admin.ai.copilot.announcement'))
    <x-admin.card title="ai.admin.copilot" icon="stars" subtitle="ai.admin.copilot_hint" data-ai-copilot="announcement"
        :data-ai-urls="json_encode(['announcement' => route('admin.ai.copilot.announcement'), 'translate' => route('admin.ai.copilot.translate'), 'shorten' => route('admin.ai.copilot.shorten')])"
        :data-ai-i18n="\App\Services\Ai\CopilotUi::i18n()">
        <div class="field">
            <label class="form-label" for="ai_brief">{{ __('ai.admin.brief') }}</label>
            <input type="text" id="ai_brief" class="form-control" maxlength="500" data-ai-brief placeholder="{{ __('ai.admin.brief_placeholder') }}">
        </div>
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-primary" data-ai-action="announcement"><i class="bi bi-magic"></i>{{ __('ai.admin.draft_announcement') }}</button>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" data-ai-action="translate" data-from="ar" data-to="en"><i class="bi bi-translate"></i>{{ __('ai.admin.translate_ar_en') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" data-ai-action="translate" data-from="en" data-to="ar"><i class="bi bi-translate"></i>{{ __('ai.admin.translate_en_ar') }}</button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ai-action="shorten"><i class="bi bi-scissors"></i>{{ __('ai.admin.shorten_content') }}</button>
            <button type="button" class="btn btn-sm btn-link d-none" data-ai-undo><i class="bi bi-arrow-counterclockwise"></i>{{ __('ai.admin.undo') }}</button>
        </div>
        <div class="small mt-2 text-muted" data-ai-status role="status" aria-live="polite"></div>
    </x-admin.card>
    @include('admin.ai._assets')
@endif
