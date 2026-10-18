@php
    $jsonName = 's['.str_replace('.', '][', $key).']';
    $jsonErr = 's.'.$key;
    $jsonVal = old($jsonErr, json_encode($tenant->setting($key, []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $jsonId = 'j_'.str_replace('.', '_', $key);
@endphp
<div class="{{ $col ?? 'col-12' }} field">
    <label class="form-label" for="{{ $jsonId }}">{{ __($label) }} <span class="pill pill-sm pill-neutral ms-1">JSON</span></label>
    <textarea name="{{ $jsonName }}" id="{{ $jsonId }}" rows="{{ $rows ?? 5 }}" dir="ltr" spellcheck="false"
              class="form-control font-monospace small {{ $errors->has($jsonErr) ? 'is-invalid' : '' }}">{{ $jsonVal }}</textarea>
    @if(!empty($help))<div class="form-text ltr text-start">{{ __($help) }}</div>@endif
    @error($jsonErr)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
