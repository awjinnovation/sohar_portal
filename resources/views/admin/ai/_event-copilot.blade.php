{{-- AI copilot for the event create/edit form (contract §A5). Fills the form fields; the admin reviews and saves. --}}
@if(\App\Services\Ai\AiUsage::copilotAvailable($brand ?? null) && auth()->user()?->can('manage events') && \Illuminate\Support\Facades\Route::has('admin.ai.copilot.event-description'))
    <x-admin.card title="ai.admin.copilot" icon="stars" subtitle="ai.admin.copilot_hint" data-ai-copilot="event"
        :data-ai-urls="json_encode(['describe' => route('admin.ai.copilot.event-description'), 'translate' => route('admin.ai.copilot.translate'), 'shorten' => route('admin.ai.copilot.shorten')])"
        :data-ai-i18n="\App\Services\Ai\CopilotUi::i18n()">
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-primary" data-ai-action="describe"><i class="bi bi-magic"></i>{{ __('ai.admin.write_description') }}</button>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" data-ai-action="translate" data-from="ar" data-to="en"><i class="bi bi-translate"></i>{{ __('ai.admin.translate_ar_en') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" data-ai-action="translate" data-from="en" data-to="ar"><i class="bi bi-translate"></i>{{ __('ai.admin.translate_en_ar') }}</button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ai-action="shorten"><i class="bi bi-scissors"></i>{{ __('ai.admin.shorten') }}</button>
            <button type="button" class="btn btn-sm btn-link d-none" data-ai-undo><i class="bi bi-arrow-counterclockwise"></i>{{ __('ai.admin.undo') }}</button>
        </div>
        <div class="small mt-2 text-muted" data-ai-status role="status" aria-live="polite"></div>
    </x-admin.card>
    @include('admin.ai._assets')
@endif
