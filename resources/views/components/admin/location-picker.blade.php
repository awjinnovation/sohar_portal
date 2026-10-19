{{-- Leaflet location picker (OpenStreetMap tiles): click or drag the pin, paste "lat, lng" or a map link,
     "use map centre", other places shown faintly.
     <x-admin.location-picker :lat="$lat" :lng="$lng" :center="[24.3, 56.7]" :others="[[lat, lng, name, color], …]" /> --}}
@props(['latName' => 'latitude', 'lngName' => 'longitude', 'lat' => null, 'lng' => null, 'center' => [24.35, 56.72], 'zoom' => 13, 'others' => [], 'required' => true, 'color' => null])
@php
    $lpLat = old($latName, $lat);
    $lpLng = old($lngName, $lng);
@endphp
<div class="location-picker" data-location-picker
     data-center='@json(['lat' => (float) $center[0], 'lng' => (float) $center[1], 'zoom' => (int) $zoom])'
     data-others='@json(array_values($others))' @if($color) data-color="{{ $color }}" @endif>
    <div class="lp-map" data-lp-map dir="ltr" role="application" aria-label="{{ __('الخريطة، انقر لتحديد الموقع') }}"></div>
    <div class="row g-2 mt-1 align-items-end">
        <div class="col-sm-4 field mb-0">
            <label class="form-label" for="lp_{{ $latName }}">{{ __('خط العرض') }}@if($required)<span class="req">*</span>@endif</label>
            <input type="number" step="0.0000001" min="-90" max="90" class="form-control ltr {{ $errors->has($latName) ? 'is-invalid' : '' }}" dir="ltr" name="{{ $latName }}" id="lp_{{ $latName }}" value="{{ $lpLat }}" data-lp-lat @required($required)>
        </div>
        <div class="col-sm-4 field mb-0">
            <label class="form-label" for="lp_{{ $lngName }}">{{ __('خط الطول') }}@if($required)<span class="req">*</span>@endif</label>
            <input type="number" step="0.0000001" min="-180" max="180" class="form-control ltr {{ $errors->has($lngName) ? 'is-invalid' : '' }}" dir="ltr" name="{{ $lngName }}" id="lp_{{ $lngName }}" value="{{ $lpLng }}" data-lp-lng @required($required)>
        </div>
        <div class="col-sm-4 d-flex gap-2">
            <button type="button" class="btn btn-tonal flex-grow-1" data-lp-center><i class="bi bi-crosshair"></i> {{ __('مركز الخريطة') }}</button>
        </div>
        <div class="col-12 field mb-0">
            <label class="form-label" for="lp_paste_{{ $latName }}">{{ __('الصق الإحداثيات أو رابط خرائط') }}</label>
            <div class="input-group">
                <input type="text" class="form-control ltr" dir="ltr" id="lp_paste_{{ $latName }}" placeholder="24.36203, 56.74929" data-lp-paste autocomplete="off">
                <button type="button" class="btn btn-tonal" data-lp-go>{{ __('انتقال') }}</button>
            </div>
            <div class="form-text" data-lp-msg data-bad="{{ __('لم نتعرّف على الإحداثيات. اكتبها بهذا الشكل 24.36203, 56.74929.') }}">{{ __('انقر على الخريطة أو اسحب الدبوس لتحديد الموقع.') }}</div>
        </div>
    </div>
    @error($latName)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @error($lngName)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
