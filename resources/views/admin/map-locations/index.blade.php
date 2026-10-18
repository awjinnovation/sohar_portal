@extends('layouts.admin')

@section('title', 'مواقع الخريطة')
@section('page-title', 'مواقع الخريطة')
@section('page-subtitle', 'إدارة المواقع والأماكن على الخريطة التفاعلية')
@php
    $pageIcon = 'geo-alt';
    $breadcrumbs = ['المحتوى' => null, 'مواقع الخريطة' => null];
    $locTypes = \App\Models\MapLocation::getTypes();
    $biIcons = ['pin-map-fill', 'geo-alt-fill', 'star-fill', 'flag-fill', 'cup-hot-fill', 'shop', 'building', 'hospital', 'life-preserver', 'p-square-fill'];
@endphp
@section('page-actions')
    <a href="{{ route('admin.map-locations.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة موقع جديد') }}
    </a>
@endsection

@push('styles')
<style>
    .pin-ico { width: 40px; height: 40px; flex: none; border-radius: 50%; display: grid; place-items: center; font-size: 17px;
        background: color-mix(in srgb, var(--pin, var(--muted)) 16%, var(--surface)); color: color-mix(in srgb, var(--pin, var(--muted)) 78%, var(--text)); }
</style>
@endpush

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="جميع المواقع" icon="geo-alt" :count="$locations->total()" search="search" search-placeholder="ابحث بالاسم أو الوصف…" :paginator="$locations" id="locationsTable">
        <x-slot:filters>
            <select name="type" class="form-select" aria-label="{{ __('النوع') }}">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach($locTypes as $key => $type)
                    <option value="{{ $key }}" @selected(request('type') == $key)>{{ $type[app()->getLocale() === 'en' ? 'en' : 'ar'] }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th>{{ __('الموقع') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('الإحداثيات') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('آخر تحديث') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($locations as $location)
            @php
                $pinHex = $location->color ? sprintf('#%06X', ((int) $location->color) & 0xFFFFFF) : null;
                $pinIcon = in_array($location->icon, $biIcons, true) ? $location->icon : 'geo-alt-fill';
            @endphp
            <tr>
                <td>
                    <div class="cell-media">
                        <span class="pin-ico" @if($pinHex) style="--pin: {{ $pinHex }}" @endif><i class="bi bi-{{ $pinIcon }}"></i></span>
                        <div style="min-width: 0">
                            <div class="cell-title">{{ $location->name_ar ?: $location->name }}</div>
                            <div class="cell-sub">{{ \Illuminate\Support\Str::limit($location->description_ar ?: $location->description, 60) }}</div>
                        </div>
                    </div>
                </td>
                <td><x-admin.status-pill :variant="$location->type === 'emergency' || $location->type === 'first_aid' ? 'error' : 'neutral'" :label="$locTypes[$location->type][app()->getLocale() === 'en' ? 'en' : 'ar'] ?? $location->type" /></td>
                <td class="num text-muted ltr text-start nowrap">{{ (float) $location->latitude }}, {{ (float) $location->longitude }}</td>
                <td><x-admin.status-pill domain="active" :status="$location->is_active" /></td>
                <td class="num text-muted">{{ $location->updated_at?->format('Y-m-d') }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.map-locations.show', $location) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.map-locations.edit', $location) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.map-locations.destroy', $location) }}" method="POST" data-confirm="{{ __('سيتم حذف الموقع «:name» من الخريطة نهائيًا.', ['name' => $location->name_ar ?: $location->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="geo-alt" title="لا توجد مواقع" text="قم بإضافة موقع جديد ليظهر على الخريطة التفاعلية.">
                <a href="{{ route('admin.map-locations.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة موقع جديد') }}</a>
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection

@push('scripts')
<script>
    // The list filters by "type" whenever the parameter is present, so do not send it empty.
    document.querySelector('#locationsTable .dt-toolbar')?.addEventListener('submit', function (e) {
        e.currentTarget.querySelectorAll('select[name="type"], input[name="search"]').forEach(function (el) {
            if (el.value === '') el.disabled = true;
        });
    });
</script>
@endpush
