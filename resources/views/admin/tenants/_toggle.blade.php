@php
    $toggleName = 's['.str_replace('.', '][', $key).']';
    $toggleErr = 's.'.$key;
    $toggleOn = filter_var(old($toggleErr, $tenant->setting($key)), FILTER_VALIDATE_BOOLEAN);
    $toggleId = 'sw_'.str_replace('.', '_', $key);
@endphp
<div class="{{ $col ?? 'col-md-4 col-sm-6' }} mb-3">
    <input type="hidden" name="{{ $toggleName }}" value="0">
    <label class="toggle-row" for="{{ $toggleId }}">
        <span class="tr-text">
            <span class="tr-label d-block">{{ __($label) }}</span>
            @if(!empty($code))<code>{{ $code }}</code>@endif
            @if(!empty($description))<span class="tr-desc d-block">{{ __($description) }}</span>@endif
        </span>
        <span class="switch">
            <input type="checkbox" role="switch" id="{{ $toggleId }}" name="{{ $toggleName }}" value="1" @checked($toggleOn)>
            <span class="track"></span>
        </span>
    </label>
    @error($toggleErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
