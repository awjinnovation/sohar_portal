{{-- Places & content admin assets (pushed once): Material Symbols Rounded (Google Fonts),
     Leaflet 1.9.4 + SortableJS 1.15.2 (cdnjs, pinned), page CSS/JS. --}}
@once
    @push('styles')
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0&display=block">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
        <link rel="stylesheet" href="{{ asset('css/admin-discover.css') }}?v={{ @filemtime(public_path('css/admin-discover.css')) }}">
    @endpush
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>window.DISCOVER_I18N = {!! json_encode([
            'saved_order' => __('حُفظ الترتيب'),
            'order_failed' => __('تعذّر حفظ الترتيب. أعد المحاولة.'),
            'edit' => __('تعديل'),
            'draft' => __('مسودة'),
            'remove' => __('حذف'),
            'new_image' => __('صورة جديدة'),
            'no_location' => __('لم يُحدَّد الموقع بعد'),
            'link_prompt' => __('اكتب عنوان الرابط'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
        <script src="{{ asset('js/admin-discover.js') }}?v={{ @filemtime(public_path('js/admin-discover.js')) }}"></script>
    @endpush
@endonce
