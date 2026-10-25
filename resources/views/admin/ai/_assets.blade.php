{{-- AI copilot assets (pushed once per page). --}}
@once
    @push('styles')
        <style>
            .ai-filled { box-shadow: 0 0 0 .2rem rgba(var(--brand-rgb), .28) !important; border-color: var(--brand) !important; transition: box-shadow .3s; }
            [data-ai-copilot] .btn .bi, [data-ai-briefing] .btn .bi { margin-inline-end: .25rem; }
            [data-ai-copilot][aria-busy="true"] { opacity: .92; }
        </style>
    @endpush
    @push('scripts')
        <script src="{{ asset('js/admin-ai.js') }}?v={{ @filemtime(public_path('js/admin-ai.js')) }}"></script>
    @endpush
@endonce
