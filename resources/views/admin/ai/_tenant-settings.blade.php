{{-- AI settings of a tenant (contract §A2) inside admin/tenants/form. Module switches (ai_assistant, ai_plan) are in the Modules card. --}}
@php
    $aiUsage = $tenant->exists ? app(\App\Services\Ai\AiUsage::class)->summary($tenant) : null;
@endphp
<x-admin.card id="sec-ai" title="ai.admin.ai_settings" icon="stars" subtitle="ai.admin.ai_settings_hint">
    @if($aiUsage)
        <p class="form-text mt-0 mb-3">
            <i class="bi bi-speedometer2"></i>
            {{ __('ai.admin.usage', ['used' => number_format($aiUsage['tokens_used']), 'budget' => number_format($aiUsage['budget']), 'requests' => number_format($aiUsage['requests']), 'usd' => number_format($aiUsage['estimated_usd'], 2)]) }}
        </p>
    @endif
    <div class="row">
        @include('admin.tenants._field', ['key' => 'ai.assistant_name.ar', 'label' => 'ai.admin.assistant_name_ar', 'col' => 'col-md-6'])
        @include('admin.tenants._field', ['key' => 'ai.assistant_name.en', 'label' => 'ai.admin.assistant_name_en', 'col' => 'col-md-6', 'ltr' => true])
        @include('admin.tenants._field', ['key' => 'ai.welcome.ar', 'label' => 'ai.admin.welcome_ar', 'col' => 'col-md-6'])
        @include('admin.tenants._field', ['key' => 'ai.welcome.en', 'label' => 'ai.admin.welcome_en', 'col' => 'col-md-6', 'ltr' => true])
        @include('admin.tenants._json', ['key' => 'ai.suggestions', 'label' => 'ai.admin.suggestions', 'rows' => 5, 'help' => 'ai.admin.suggestions_help'])
        @foreach(['ar', 'en'] as $aiLang)
            @php
                $aiInfoErr = 's.ai.venue_info.'.$aiLang;
                $aiInfoVal = old($aiInfoErr, $tenant->setting('ai.venue_info.'.$aiLang));
            @endphp
            <div class="col-md-6 field">
                <label class="form-label" for="f_ai_venue_info_{{ $aiLang }}">{{ __('ai.admin.venue_info_'.$aiLang) }}</label>
                <textarea name="s[ai][venue_info][{{ $aiLang }}]" id="f_ai_venue_info_{{ $aiLang }}" rows="4" maxlength="3000" @if($aiLang === 'en') dir="ltr" @endif
                          class="form-control {{ $errors->has($aiInfoErr) ? 'is-invalid' : '' }}">{{ $aiInfoVal }}</textarea>
                <div class="form-text">{{ __('ai.admin.venue_info_help') }}</div>
                @error($aiInfoErr)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
        @include('admin.tenants._field', ['key' => 'ai.monthly_token_budget', 'label' => 'ai.admin.budget', 'type' => 'number', 'col' => 'col-md-6', 'ltr' => true, 'help' => 'ai.admin.budget_help'])
        @include('admin.tenants._toggle', ['key' => 'ai.copilot_enabled', 'label' => 'ai.admin.copilot_enabled', 'col' => 'col-md-6'])
    </div>
</x-admin.card>
