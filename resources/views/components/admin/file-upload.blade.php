{{-- <x-admin.file-upload name="logo" label="…" :current="$url" accept="image/*" help="…" /> --}}
@props(['name', 'label' => null, 'current' => null, 'accept' => 'image/*', 'help' => null, 'id' => null, 'multiple' => false, 'icon' => 'cloud-arrow-up'])
@php $fuId = $id ?? 'up_'.preg_replace('/[^A-Za-z0-9_]/', '_', $name); $fuErr = trim(str_replace(['[', ']'], ['.', ''], $name), '.'); @endphp
<div class="field">
    @if($label)<label for="{{ $fuId }}" class="form-label">{{ __($label) }}</label>@endif
    <div class="upload" data-upload>
        <div class="preview">@if($current)<img src="{{ $current }}" alt="">@else<i class="bi bi-{{ $icon }}"></i>@endif</div>
        <div class="min-w-0">
            <div class="u-title">{{ __('اسحب الملف هنا أو اضغط للاختيار') }}</div>
            @if($help)<div class="u-help">{{ __($help) }}</div>@endif
            <div class="u-file"></div>
        </div>
        <input type="file" id="{{ $fuId }}" name="{{ $name }}" accept="{{ $accept }}" @if($multiple) multiple @endif {{ $attributes }}>
    </div>
    @error($fuErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
