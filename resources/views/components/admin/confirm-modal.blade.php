{{-- Global confirm dialog. Any <form data-confirm="message" [data-confirm-tone="primary"] [data-confirm-ok="label"]> or <a data-confirm> uses it. --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true" data-default-title="{{ __('تأكيد الإجراء') }}" data-default-ok="{{ __('تأكيد') }}">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="confirm-ico tone-error"><i class="bi bi-exclamation-triangle"></i></div>
                <h2 class="h5 fw-bold mb-2" data-confirm-title>{{ __('تأكيد الإجراء') }}</h2>
                <p class="text-muted mb-4" data-confirm-message></p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                    <button type="button" class="btn btn-danger" data-confirm-ok>{{ __('تأكيد') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
