{{-- Draft / published segmented control. <x-admin.status-select :value="$model->status" /> --}}
@props(['name' => 'status', 'value' => 'draft'])
@php $ssVal = old($name, $value ?: 'draft'); @endphp
<div class="field">
    <span class="form-label d-block">{{ __('الحالة') }}</span>
    <div class="btn-group w-100 status-seg" role="radiogroup" aria-label="{{ __('الحالة') }}">
        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $name }}_draft" value="draft" @checked($ssVal === 'draft')>
        <label class="btn btn-outline-secondary" for="{{ $name }}_draft"><i class="bi bi-pencil-square"></i> {{ __('مسودة') }}</label>
        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $name }}_published" value="published" @checked($ssVal === 'published')>
        <label class="btn btn-outline-success" for="{{ $name }}_published"><i class="bi bi-broadcast"></i> {{ __('منشور') }}</label>
    </div>
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
