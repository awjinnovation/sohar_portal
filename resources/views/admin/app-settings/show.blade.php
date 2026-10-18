@extends('layouts.admin')

@section('title', 'تفاصيل الإعداد')
@section('page-title', 'تفاصيل إعدادات التطبيق')
@php
    $pageIcon = 'sliders';
    $breadcrumbs = ['المنظمة' => null, 'إعدادات التطبيق' => route('admin.app-settings.index'), $appSetting->setting_key => null];
    $typeLabels = ['string' => __('نص'), 'integer' => __('رقم صحيح'), 'boolean' => __('منطقي'), 'decimal' => __('عشري'), 'json' => 'JSON'];
    $jsonPretty = null;
    if ($appSetting->setting_type === 'json') {
        $decoded = json_decode((string) $appSetting->setting_value, true);
        $jsonPretty = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $appSetting->setting_value;
    }
@endphp
@section('page-actions')
    <a href="{{ route('admin.app-settings.edit', $appSetting) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.app-settings.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="الإعداد" icon="sliders">
                <dl class="dl">
                    <dt>{{ __('مفتاح الإعداد') }}</dt><dd><code class="ltr">{{ $appSetting->setting_key }}</code></dd>
                    <dt>{{ __('النوع') }}</dt><dd><span class="pill pill-brand pill-sm">{{ $typeLabels[$appSetting->setting_type] ?? $appSetting->setting_type }}</span></dd>
                    <dt>{{ __('القيمة') }}</dt>
                    <dd>
                        @if($appSetting->setting_type === 'boolean')
                            <x-admin.status-pill :variant="$appSetting->value ? 'success' : 'neutral'" :label="$appSetting->value ? 'نعم' : 'لا'" />
                        @elseif($jsonPretty !== null)
                            <pre class="kv mb-0 small ltr" style="white-space: pre-wrap">{{ $jsonPretty }}</pre>
                        @else
                            <span class="{{ in_array($appSetting->setting_type, ['integer', 'decimal']) ? 'num fw-semibold' : '' }}">{{ $appSetting->setting_value }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('الوصف') }}</dt><dd>{{ $appSetting->description ?: '—' }}</dd>
                </dl>
            </x-admin.card>
        </div>
        <div class="col-xl-4">
            <x-admin.card title="معلومات" icon="info-circle">
                <dl class="dl">
                    <dt>{{ __('المعرف') }}</dt><dd class="num">{{ $appSetting->id }}</dd>
                    <dt>{{ __('الظهور') }}</dt>
                    <dd>
                        @if($appSetting->is_public)
                            <x-admin.status-pill variant="success" label="متاح للعموم" icon="globe" />
                        @else
                            <x-admin.status-pill variant="neutral" label="خاص" icon="lock" />
                        @endif
                    </dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt><dd><span class="num ltr">{{ $appSetting->created_at?->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('آخر تحديث') }}</dt><dd><span class="num ltr">{{ $appSetting->updated_at?->format('Y-m-d H:i') }}</span></dd>
                </dl>
                <x-slot:footer>
                    <form action="{{ route('admin.app-settings.destroy', $appSetting) }}" method="POST" data-confirm="{{ __('سيتم حذف الإعداد «:key» نهائيًا وقد يتأثر التطبيق.', ['key' => $appSetting->setting_key]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> {{ __('حذف الإعداد') }}</button>
                    </form>
                </x-slot:footer>
            </x-admin.card>
        </div>
    </div>
@endsection
