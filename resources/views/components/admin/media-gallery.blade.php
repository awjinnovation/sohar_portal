{{-- Gallery manager: existing images (drag to reorder, mark for removal) + new uploads with previews.
     Posts gallery_order[] (ids in order), remove_media[] (ids) and gallery[] (files).
     <x-admin.media-gallery :media="$model->getMedia('gallery')" /> --}}
@props(['media' => collect(), 'name' => 'gallery', 'label' => 'معرض الصور', 'help' => 'JPG أو PNG أو WebP، حتى 8 ميغابايت للصورة. اسحب الصور لترتيبها.'])
@php $mgErr = $name; @endphp
<div class="field media-gallery" data-gallery>
    @if($label)<label class="form-label">{{ __($label) }}</label>@endif
    <div class="mg-grid" data-mg-grid>
        @foreach($media as $m)
            @php $img = \App\Support\MediaPresenter::image($m); @endphp
            <div class="mg-item" data-id="{{ $m->id }}">
                <img src="{{ $img['thumb_url'] }}" alt="" loading="lazy">
                <input type="hidden" name="gallery_order[]" value="{{ $m->id }}">
                <span class="mg-handle" title="{{ __('اسحب للترتيب') }}"><i class="bi bi-grip-vertical"></i></span>
                <label class="mg-remove" title="{{ __('حذف الصورة') }}">
                    <input type="checkbox" name="remove_media[]" value="{{ $m->id }}" class="visually-hidden" data-mg-remove>
                    <i class="bi bi-trash"></i><span class="visually-hidden">{{ __('حذف الصورة') }}</span>
                </label>
            </div>
        @endforeach
        <label class="mg-add" title="{{ __('إضافة صور') }}">
            <input type="file" name="{{ $name }}[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple class="visually-hidden" data-mg-input>
            <i class="bi bi-plus-lg"></i><span>{{ __('إضافة صور') }}</span>
        </label>
    </div>
    <div class="mg-new" data-mg-new></div>
    @error($mgErr)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @error($mgErr.'.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ __($help) }}</div>@endif
</div>
