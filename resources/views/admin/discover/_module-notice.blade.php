{{-- Warn when the app module of this section is switched off for the organization. --}}
@php $mnTenant = $currentTenant ?? $brand ?? null; @endphp
@if($mnTenant && ! $mnTenant->moduleEnabled($module))
    <div class="alert alert-warning d-flex gap-2 align-items-start module-off" role="status">
        <i class="bi bi-eye-slash fs-5"></i>
        <div>{{ __($module === 'places' ? 'وحدة الأماكن متوقفة في التطبيق، فلن يرى الزوار هذه الأماكن حتى تفعّلها من إعدادات المنظمة.' : 'وحدة المحتوى متوقفة في التطبيق، فلن يرى الزوار هذه المجموعات والصفحات حتى تفعّلها من إعدادات المنظمة.') }}</div>
    </div>
@endif
