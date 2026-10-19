@extends('layouts.admin')

@section('title', $vendor->localizedName('ar'))
@section('page-title', __('التاجر: :name', ['name' => $vendor->localizedName('ar')]))
@section('page-subtitle', \App\Http\Controllers\Admin\VendorController::CATEGORIES[$vendor->category] ?? ($vendor->category ?: 'بدون تصنيف'))
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'shop-window';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'التجار' => route('admin.vendors.index'), $vendor->localizedName('ar') => null];
    $categories = \App\Http\Controllers\Admin\VendorController::CATEGORIES;
@endphp
@section('page-actions')
    <a href="{{ route('admin.vendors.edit', $vendor) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    <a href="{{ route('admin.vendors.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="مبيعات اليوم" :money="$stats['today']" icon="calendar-check" tone="brand" />
        <x-admin.stat-tile label="إجمالي المبيعات" :money="$stats['total']" icon="graph-up-arrow" tone="success" />
        <x-admin.stat-tile label="عدد المعاملات" :value="$stats['count']" icon="receipt" tone="neutral" />
        <x-admin.stat-tile label="المبالغ المستردة" :money="$stats['refunds']" icon="arrow-counterclockwise" tone="neutral" />
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <x-admin.card title="البيانات" icon="info-circle" class="h-100 mb-0">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="cell-media">
                        @if($vendor->logoUrl())
                            <img src="{{ $vendor->logoUrl() }}" alt="" style="width: 64px; height: 64px; object-fit: contain" class="border">
                        @else
                            <span class="ph" style="width: 64px; height: 64px; font-size: 26px"><i class="bi bi-shop"></i></span>
                        @endif
                    </span>
                    <div>
                        <div class="cell-title fs-6">{{ $vendor->name['ar'] ?? '' }}</div>
                        <div class="cell-sub ltr text-start">{{ $vendor->name['en'] ?? '' }}</div>
                        <div class="mt-1"><x-admin.status-pill domain="active" :status="$vendor->is_active" size="sm" /></div>
                    </div>
                </div>
                <dl class="dl">
                    <dt>{{ __('الوصف') }}</dt><dd>{{ ($vendor->description['ar'] ?? '') ?: '—' }}</dd>
                    <dt>{{ __('التصنيف') }}</dt><dd>{{ __($categories[$vendor->category] ?? ($vendor->category ?: '—')) }}</dd>
                    <dt>{{ __('المطعم المرتبط') }}</dt><dd>{{ $vendor->restaurant ? ($vendor->restaurant->name_ar ?: $vendor->restaurant->name) : '—' }}</dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-xl-7">
            <x-admin.card title="الموظفون" icon="people" :subtitle="$vendor->staff->count() ? __('موظفون يستقبلون الدفع: :count', ['count' => $vendor->staff->count()]) : 'أضف موظفين لاستقبال الدفع في نقطة البيع'" :flush="true" class="h-100 mb-0">
                <form action="{{ route('admin.vendors.staff.attach', $vendor) }}" method="POST" class="row g-2 align-items-end p-3 border-bottom">
                    @csrf
                    <x-admin.form-field col="col-md-6" name="identifier" label="البريد الإلكتروني أو رقم الهاتف" :value="old('identifier')"
                        placeholder="name@example.com" required />
                    <x-admin.form-field col="col-md-3 col-7" name="role" label="الدور" type="select" :value="old('role')" :options="AdminLabels::VENDOR_STAFF_ROLES" />
                    <div class="col-md-3 col-5 field">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> {{ __('إضافة') }}</button>
                    </div>
                </form>

                <ul class="tx-list">
                    @forelse($vendor->staff as $member)
                        <li class="tx-item">
                            <span class="avatar">{{ mb_substr($member->name ?? '?', 0, 1) }}</span>
                            <div class="tx-main">
                                <div class="tx-title">{{ $member->name }}</div>
                                <div class="tx-sub ltr text-start">{{ collect([$member->phone_number, $member->email])->filter()->implode(' · ') }}</div>
                            </div>
                            <x-admin.status-pill :variant="$member->pivot->role === 'manager' ? 'brand' : 'neutral'" :label="AdminLabels::label(AdminLabels::VENDOR_STAFF_ROLES, $member->pivot->role)" size="sm" />
                            <form action="{{ route('admin.vendors.staff.detach', [$vendor, $member]) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('إزالة :name من موظفي التاجر؟ لن يستطيع بعدها استقبال الدفع لهذا التاجر.', ['name' => $member->name]) }}" data-confirm-ok="{{ __('إزالة') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-btn danger" title="{{ __('إزالة الموظف') }}"><i class="bi bi-person-dash"></i></button>
                            </form>
                        </li>
                    @empty
                        <li><x-admin.empty-state icon="people" title="لا يوجد موظفون" text="أضف موظفًا بالبريد الإلكتروني أو رقم الهاتف." /></li>
                    @endforelse
                </ul>
            </x-admin.card>
        </div>
    </div>

    <x-admin.data-table title="آخر المعاملات" icon="journal-text" :count="$transactions->total()" :paginator="$transactions">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('المعاملة') }}</th>
            <th>{{ __('الزائر') }}</th>
            <th>{{ __('المبلغ من محفظة الزائر') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th class="text-end"></th>
        </x-slot:head>

        @forelse($transactions as $tx)
            <tr>
                <td class="num text-muted">{{ $tx->id }}</td>
                <td>
                    <div class="cell-title text-nowrap">{{ AdminLabels::label(AdminLabels::TX_TYPES, $tx->type) }}</div>
                    <div class="cell-sub">{{ AdminLabels::label(AdminLabels::BUCKETS, $tx->bucket()) }}</div>
                </td>
                <td class="text-nowrap">{{ $tx->user?->name ?? '-' }}</td>
                <td><x-admin.money :minor="$tx->amount_minor" :direction="$tx->direction" /></td>
                <td><x-admin.status-pill domain="tx" :status="$tx->status" /></td>
                <td class="num text-muted"><span class="ltr">{{ $tx->created_at?->format('Y-m-d H:i') }}</span></td>
                <td class="text-end">
                    @can('manage wallets')
                        <a href="{{ route('admin.wallets.show', $tx->wallet_id) }}" class="icon-btn brand" title="{{ __('فتح المحفظة') }}"><i class="bi bi-wallet2"></i></a>
                    @endcan
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="journal-text" title="لا توجد معاملات" text="ستظهر هنا مبيعات التاجر من محافظ الزوار." />
        @endforelse
    </x-admin.data-table>
@endsection
