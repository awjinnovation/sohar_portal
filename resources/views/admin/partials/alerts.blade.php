{{-- Flash messages are shown as toasts by the layout (<x-admin.toast>); only validation errors are listed inline here. --}}
@if($errors->any())
    <div class="alert alert-danger d-flex gap-3 align-items-start" role="alert">
        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
        <div class="flex-grow-1">
            <div class="fw-bold mb-1">{{ __('يرجى تصحيح الأخطاء التالية') }}</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
