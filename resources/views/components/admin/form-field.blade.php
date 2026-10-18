{{-- <x-admin.form-field name="title_ar" label="…" :value="old('title_ar', $m->title_ar)" required help="…" type="text|email|number|textarea|select|date|datetime-local|password|url|time" :options="['k' => 'Label']" rows="4" dir="ltr" col="col-md-6" /> --}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'id' => null, 'required' => false, 'help' => null, 'options' => [], 'placeholder' => null, 'rows' => 4, 'dir' => null, 'col' => null, 'step' => null, 'min' => null, 'max' => null, 'accept' => null])
@php
    $fId = $id ?? preg_replace('/[^A-Za-z0-9_\-]/', '_', trim(str_replace(['[', ']'], ['_', ''], $name), '_'));
    $fErr = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $fVal = $value ?? old($fErr);
    $fInvalid = $errors->has($fErr) ? ' is-invalid' : '';
@endphp
<div class="{{ $col ? $col.' ' : '' }}field">
    @if($label)<label for="{{ $fId }}" class="form-label">{{ __($label) }}@if($required)<span class="req">*</span>@endif</label>@endif
    @if($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $fId }}" rows="{{ $rows }}" @if($dir) dir="{{ $dir }}" @endif @if($placeholder) placeholder="{{ __($placeholder) }}" @endif @required($required) {{ $attributes->merge(['class' => 'form-control'.$fInvalid]) }}>{{ $fVal }}</textarea>
    @elseif($type === 'select')
        <select name="{{ $name }}" id="{{ $fId }}" @required($required) {{ $attributes->merge(['class' => 'form-select'.$fInvalid]) }}>
            @if($placeholder !== null)<option value="">{{ __((string) $placeholder) }}</option>@endif
            @foreach($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $fVal === (string) $optValue)>{{ is_string($optLabel) ? __($optLabel) : $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $fId }}" @if($type !== 'password' && $type !== 'file') value="{{ $fVal }}" @endif @if($dir) dir="{{ $dir }}" @endif
               @if($placeholder) placeholder="{{ __($placeholder) }}" @endif @if($step) step="{{ $step }}" @endif @if($min !== null) min="{{ $min }}" @endif @if($max !== null) max="{{ $max }}" @endif @if($accept) accept="{{ $accept }}" @endif
               @required($required) {{ $attributes->merge(['class' => 'form-control'.$fInvalid]) }}>
    @endif
    {{ $slot }}
    @error($fErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ __($help) }}</div>@endif
</div>
