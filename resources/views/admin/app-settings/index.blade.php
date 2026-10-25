@extends('layouts.admin')

@section('title', 'إعدادات التطبيق')
@section('page-title', 'إعدادات التطبيق')
@section('page-subtitle', 'إدارة إعدادات تطبيق الهاتف')
@php
    $pageIcon = 'sliders';
    $breadcrumbs = ['المنظمة' => null, 'إعدادات التطبيق' => null];
    $typeMeta = [
        'string' => [__('نص'), 'brand'],
        'integer' => [__('عدد صحيح'), 'success'],
        'boolean' => [__('قيمة منطقية'), 'warning'],
        'decimal' => [__('عدد عشري'), 'info'],
        'json' => ['JSON', 'neutral'],
    ];
@endphp
@section('page-actions')
    <a href="{{ route('admin.app-settings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة إعداد جديد') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="إعدادات عامة" :value="\App\Models\AppSetting::where('is_public', true)->count()" icon="globe" tone="brand" />
        <x-admin.stat-tile label="إعدادات منطقية" :value="\App\Models\AppSetting::where('setting_type', 'boolean')->count()" icon="toggle-on" tone="success" />
        <x-admin.stat-tile label="إعدادات JSON" :value="\App\Models\AppSetting::where('setting_type', 'json')->count()" icon="braces" tone="info" />
        <x-admin.stat-tile label="إعدادات الألوان" :value="\App\Models\AppSetting::where('setting_key', 'like', '%color%')->count()" icon="palette" tone="accent" />
    </div>

    <x-admin.data-table title="الإعدادات" icon="sliders" :count="$settings->total()" search="search" search-placeholder="البحث بالمفتاح أو الوصف" :paginator="$settings">
        <x-slot:filters>
            <select name="type" class="form-select" aria-label="{{ __('النوع') }}">
                <option value="">{{ __('جميع الأنواع') }}</option>
                @foreach($typeMeta as $typeKey => [$typeLabel, $typeTone])
                    <option value="{{ $typeKey }}" @selected(request('type') == $typeKey)>{{ $typeLabel }} ({{ ucfirst($typeKey) }})</option>
                @endforeach
            </select>
        </x-slot:filters>

        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('مفتاح الإعداد') }}</th>
            <th>{{ __('القيمة') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('الظهور') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($settings as $setting)
            @php [$typeLabel, $typeTone] = $typeMeta[$setting->setting_type] ?? [$setting->setting_type, 'neutral']; @endphp
            <tr>
                <td class="num text-muted">{{ $setting->id }}</td>
                <td>
                    <code class="ltr">{{ $setting->setting_key }}</code>
                    @if($setting->description)
                        <div class="cell-sub mt-1">{{ \Illuminate\Support\Str::limit($setting->description, 60) }}</div>
                    @endif
                </td>
                <td>
                    @if($setting->setting_type == 'boolean')
                        @if($setting->value)
                            <x-admin.status-pill variant="success" label="نعم" icon="check-lg" size="sm" />
                        @else
                            <x-admin.status-pill variant="neutral" label="لا" icon="x-lg" size="sm" />
                        @endif
                    @elseif($setting->setting_type == 'json')
                        <span class="pill pill-info pill-sm"><i class="bi bi-braces"></i>JSON</span>
                    @else
                        <span class="{{ in_array($setting->setting_type, ['integer', 'decimal']) ? 'num fw-semibold' : 'fw-medium' }}">{{ \Illuminate\Support\Str::limit($setting->setting_value, 50) }}</span>
                    @endif
                </td>
                <td><span class="pill pill-{{ $typeTone }} pill-sm">{{ $typeLabel }}</span></td>
                <td>
                    @if($setting->is_public)
                        <x-admin.status-pill variant="success" label="عام" icon="globe" size="sm" />
                    @else
                        <x-admin.status-pill variant="neutral" label="خاص" icon="lock" size="sm" />
                    @endif
                </td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.app-settings.show', $setting) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.app-settings.edit', $setting) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.app-settings.destroy', $setting) }}" method="POST" data-confirm="{{ __('سيُحذف الإعداد «:key» نهائيًا، وقد يتأثر التطبيق.', ['key' => $setting->setting_key]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="sliders" title="لا توجد إعدادات"
                :text="request('search') || request('type') ? 'لا توجد نتائج مطابقة للتصفية.' : 'أضف إعدادًا ليقرأه تطبيق الهاتف.'">
                <a href="{{ route('admin.app-settings.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة إعداد جديد') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
