{{-- Material Symbols Rounded icon picker: text input + live preview + searchable grid.
     <x-admin.icon-picker name="icon" :value="$icon" :fallback="$categoryIcon" label="…" help="…" /> --}}
@props(['name' => 'icon', 'id' => null, 'value' => null, 'fallback' => null, 'label' => 'الأيقونة', 'help' => null, 'required' => false, 'color' => null])
@php
    $ipId = $id ?? 'icon_'.preg_replace('/[^A-Za-z0-9_]/', '_', $name);
    $ipErr = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $ipShown = $value ?: ($fallback ?: 'place');
@endphp
<div class="field icon-picker" data-icon-picker data-fallback="{{ $fallback }}">
    @if($label)<label for="{{ $ipId }}" class="form-label">{{ __($label) }}@if($required)<span class="req">*</span>@endif</label>@endif
    <div class="d-flex gap-2 align-items-center">
        <span class="ip-preview" data-ip-preview @if($color) style="--ip: {{ $color }}" @endif aria-hidden="true"><span class="msr">{{ $ipShown }}</span></span>
        <input type="text" name="{{ $name }}" id="{{ $ipId }}" value="{{ $value }}" class="form-control ltr {{ $errors->has($ipErr) ? 'is-invalid' : '' }}" dir="ltr"
               placeholder="{{ $fallback ?: 'place' }}" autocomplete="off" spellcheck="false" maxlength="64" @required($required) data-ip-input>
        <button type="button" class="btn btn-tonal text-nowrap" data-ip-toggle aria-expanded="false"><i class="bi bi-grid-3x3-gap"></i> {{ __('اختيار') }}</button>
    </div>
    <div class="ip-panel d-none" data-ip-panel>
        <input type="search" class="form-control form-control-sm mb-2" placeholder="{{ __('ابحث عن أيقونة بالإنجليزية، مثل fort أو beach') }}" data-ip-search dir="ltr">
        <div class="ip-grid" data-ip-grid role="listbox" aria-label="{{ __('الأيقونات') }}">
            @foreach(\App\Support\MaterialIcons::all() as $iconName)
                <button type="button" class="ip-item" data-icon="{{ $iconName }}" title="{{ $iconName }}" role="option"><span class="msr">{{ $iconName }}</span><small>{{ $iconName }}</small></button>
            @endforeach
        </div>
    </div>
    @error($ipErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ __($help) }}</div>@endif
</div>
