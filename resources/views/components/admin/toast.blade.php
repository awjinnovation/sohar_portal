{{-- Flash messages as toasts (session success/error/warning/status). Validation errors stay inline. --}}
<div class="toast-stack" id="toastStack" aria-live="polite">
    @foreach(['success' => 'check-circle-fill', 'status' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'] as $flashKey => $flashIcon)
        @if(session($flashKey) && is_string(session($flashKey)))
            <div class="x-toast {{ $flashKey === 'status' ? 'success' : $flashKey }}" role="status">
                <i class="lead bi bi-{{ $flashIcon }}"></i>
                <div class="msg">{{ __(session($flashKey)) }}</div>
                <button type="button" aria-label="{{ __('إغلاق') }}"><i class="bi bi-x-lg"></i></button>
            </div>
        @endif
    @endforeach
</div>
