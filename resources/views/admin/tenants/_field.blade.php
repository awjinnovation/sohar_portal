@php
    $fieldName = 's['.str_replace('.', '][', $key).']';
    $fieldErr = 's.'.$key;
    $fieldVal = old($fieldErr, $tenant->setting($key));
    if (is_array($fieldVal)) {
        $fieldVal = implode(', ', $fieldVal);
    } elseif (is_bool($fieldVal)) {
        $fieldVal = $fieldVal ? '1' : '0';
    }
    $fieldId = $inputId ?? 'f_'.str_replace('.', '_', $key);
@endphp
<div class="{{ $col ?? 'col-md-6' }} field">
    <label class="form-label" for="{{ $fieldId }}">{{ __($label) }}@if(!empty($required))<span class="req">*</span>@endif</label>
    <input type="{{ $type ?? 'text' }}" name="{{ $fieldName }}" value="{{ $fieldVal }}" id="{{ $fieldId }}"
           class="form-control {{ $errors->has($fieldErr) ? 'is-invalid' : '' }}"
           @if(!empty($step)) step="{{ $step }}" @endif
           @if(!empty($ltr)) dir="ltr" @endif
           @if(!empty($list)) list="{{ $list }}" @endif
           placeholder="{{ isset($placeholder) ? __($placeholder) : '' }}">
    @if(!empty($help))<div class="form-text">{{ __($help) }}</div>@endif
    @error($fieldErr)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
