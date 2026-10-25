{{-- Switch row. Unchecked sends nothing (like a plain checkbox); :hidden="true" also posts $offValue.
     <x-admin.toggle name="is_active" label="…" :checked="old('is_active', $m->is_active)" description="…" code="key" /> --}}
@props(['name', 'label', 'checked' => false, 'value' => '1', 'offValue' => '0', 'hidden' => false, 'id' => null, 'description' => null, 'code' => null, 'col' => null])
@php $tId = $id ?? 'tg_'.preg_replace('/[^A-Za-z0-9_]/', '_', $name); @endphp
<div class="{{ $col ? $col.' mb-3' : 'mb-3' }}">
    <label class="toggle-row" for="{{ $tId }}">
        <span class="tr-text">
            <span class="tr-label d-block">{{ __($label) }}</span>
            @if($description)<span class="tr-desc d-block">{{ __($description) }}</span>@endif
            @if($code)<code>{{ $code }}</code>@endif
        </span>
        @if($hidden)<input type="hidden" name="{{ $name }}" value="{{ $offValue }}">@endif
        <span class="switch">
            <input type="checkbox" role="switch" id="{{ $tId }}" name="{{ $name }}" value="{{ $value }}" @checked((bool) $checked) {{ $attributes }}>
            <span class="track"></span>
        </span>
    </label>
    @error(trim(str_replace(['[', ']'], ['.', ''], $name), '.'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
