{{--
    All tickets on the page at once (public/js/ticket-export.js): one PDF with a
    page per ticket to keep on the device, the same PDF to share (WhatsApp, mail;
    shown only where the browser shares files) and all tickets as images.
    @include('partials.ticket-export', ['cards' => '<CSS selector of the ticket cards>', 'count' => n, 'lang' => 'ar|en', 'file' => 'name', 'title' => '…'])
--}}
@php
    $exportAr = ($lang ?? 'ar') === 'ar';
    $tx = fn ($ar, $en) => $exportAr ? $ar : $en;
    $count = (int) ($count ?? 1);
@endphp
<div class="ticket-export no-print" data-ticket-export data-cards="{{ $cards }}"
     data-file="{{ \Illuminate\Support\Str::slug($file ?? 'tickets', '_') ?: 'tickets' }}_tickets" data-title="{{ $title ?? '' }}"
     data-preparing="{{ $tx('جارٍ تجهيز التذاكر', 'Preparing your tickets') }}">
    <button type="button" class="btn btn-primary" data-export="pdf">
        <i class="bi bi-file-earmark-pdf"></i> <span>{{ $count > 1 ? $tx('تنزيل كل التذاكر في ملف PDF', 'Download all tickets as one PDF') : $tx('تنزيل التذكرة PDF', 'Download ticket as PDF') }}</span>
    </button>
    <button type="button" class="btn btn-tonal" data-export="share" hidden>
        <i class="bi bi-share"></i> <span>{{ $tx('مشاركة ملف PDF (واتساب وغيره)', 'Share PDF (WhatsApp and more)') }}</span>
    </button>
    @if($count > 1)
        <button type="button" class="btn btn-tonal" data-export="images">
            <i class="bi bi-images"></i> <span>{{ $tx('حفظ كل التذاكر صورًا', 'Save all tickets as images') }}</span>
        </button>
    @endif
    <p class="ticket-export-error" data-export-error hidden>{{ $tx('تعذّر تجهيز الملف. أعد المحاولة، أو احفظ كل تذكرة على حدة.', 'Could not prepare the file. Try again, or save each ticket on its own.') }}</p>
</div>
@once
    @push('head')
        <style>
            .ticket-export { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; }
            .ticket-export .btn { flex: 1 1 100%; min-height: 46px; font-size: 14.5px; justify-content: center; }
            .ticket-export .btn-tonal { flex: 1 1 calc(50% - 4px); }
            .ticket-export .btn[hidden] { display: none; }
            .ticket-export-error { flex-basis: 100%; margin: 2px 0 0; color: var(--error, #B91C1C); font-size: 13.5px; }
        </style>
    @endpush
    @push('scripts')
        <script src="{{ asset('js/ticket-export.js') }}?v={{ @filemtime(public_path('js/ticket-export.js')) }}" defer></script>
    @endpush
@endonce
