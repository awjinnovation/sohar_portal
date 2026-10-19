{{-- Arabic / English tabs for localized fields (AR first, EN pane is LTR).
     <x-admin.lang-tabs id="content"><x-slot:ar>…</x-slot:ar><x-slot:en>…</x-slot:en></x-admin.lang-tabs> --}}
@props(['id' => 'lang'])
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="{{ $id }}-ar-tab" data-bs-toggle="tab" data-bs-target="#{{ $id }}-ar" type="button" role="tab" aria-controls="{{ $id }}-ar" aria-selected="true">{{ __('العربية') }}</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="{{ $id }}-en-tab" data-bs-toggle="tab" data-bs-target="#{{ $id }}-en" type="button" role="tab" aria-controls="{{ $id }}-en" aria-selected="false" lang="en">English</button>
    </li>
</ul>
<div class="tab-content border border-top-0 p-3 lang-panes">
    <div class="tab-pane fade show active" id="{{ $id }}-ar" role="tabpanel" aria-labelledby="{{ $id }}-ar-tab" dir="rtl" lang="ar">{{ $ar }}</div>
    <div class="tab-pane fade" id="{{ $id }}-en" role="tabpanel" aria-labelledby="{{ $id }}-en-tab" dir="ltr" lang="en">{{ $en }}</div>
</div>
