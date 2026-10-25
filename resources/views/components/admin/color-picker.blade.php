{{-- Color input (swatch + hex text) with a live contrast chip.
     <x-admin.color-picker name="s[theme][colors][primary]" id="color_primary" label="…" :value="$hex" against="#FFFFFF" :required="true" code="primary" /> --}}
@props(['name', 'id', 'label' => null, 'value' => null, 'against' => null, 'required' => false, 'placeholder' => '#RRGGBB', 'code' => null, 'help' => null])
@php
    $cpErr = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $cpHex = \App\Support\ColorGuard::normalize($value);
@endphp
<div class="field">
    @if($label)
        <label for="{{ $id }}" class="form-label d-flex align-items-center gap-2">
            <span>{{ __($label) }}</span>@if($code)<code class="small">{{ $code }}</code>@endif
            @if($against)<span class="ms-auto" data-contrast></span>@endif
        </label>
    @endif
    <div class="color-field" data-color-field @if($against) data-against="{{ $against }}" @endif>
        <span class="swatch"><span style="background: {{ $cpHex ?? 'transparent' }}"></span>
            <input type="color" value="{{ strtolower($cpHex ?? '#ffffff') }}" aria-label="{{ __($label) }}" tabindex="-1"></span>
        <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" dir="ltr" maxlength="9" autocomplete="off"
               placeholder="{{ $placeholder }}" @required($required) {{ $attributes->merge(['class' => 'form-control js-color-text'.($errors->has($cpErr) ? ' is-invalid' : '')]) }}>
    </div>
    @error($cpErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ __($help) }}</div>@endif
</div>
