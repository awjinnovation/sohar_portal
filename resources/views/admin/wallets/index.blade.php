@extends('layouts.admin')

@section('title', 'المحافظ')
@section('page-title', 'إدارة المحافظ')
@section('page-subtitle', 'أرصدة الزوار النقدية والترويجية والتأمينات المحجوزة')
@php
    $pageIcon = 'wallet2';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المحافظ' => null];
@endphp

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="إجمالي الأرصدة" :money="$totals['balance']" icon="wallet2" tone="brand" hint="النقدي والترويجي في جميع المحافظ" />
        <x-admin.stat-tile label="المبالغ المحجوزة للتأمينات" :money="$totals['held']" icon="safe2" tone="info" hint="غير متاحة للإنفاق حتى الإرجاع" />
        <x-admin.stat-tile label="عدد المحافظ" :value="$totals['count']" icon="people" tone="neutral" />
        <x-admin.stat-tile label="المحافظ المجمّدة" :value="$totals['frozen']" icon="lock" :tone="$totals['frozen'] > 0 ? 'error' : 'neutral'"
            :href="route('admin.wallets.index', ['status' => 'frozen'])" hint="عرض المحافظ المجمّدة" />
    </div>

    @if($usersWithoutWallet->isNotEmpty())
        <x-admin.card title="مستخدمون بلا محفظة في هذه المنظمة" icon="person-plus" subtitle="لا تُنشأ المحفظة إلا عند طلب ذلك صراحةً أو عند أول عملية للمستخدم" :flush="true">
            <ul class="tx-list">
                @foreach($usersWithoutWallet as $u)
                    <li class="tx-item">
                        <span class="avatar">{{ mb_substr($u->name ?? '?', 0, 1) }}</span>
                        <div class="tx-main">
                            <div class="tx-title">{{ $u->name }}</div>
                            <div class="tx-sub ltr text-start">{{ collect([$u->phone_number, $u->email])->filter()->implode(' · ') }}</div>
                        </div>
                        <a href="{{ route('admin.wallets.user', $u) }}" class="btn btn-tonal btn-sm">
                            <i class="bi bi-wallet2"></i> {{ __('فتح المحفظة') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endif

    <x-admin.data-table title="جميع المحافظ" icon="wallet2" :count="$wallets->total()" search="q"
        search-placeholder="ابحث بالاسم أو رقم الهاتف أو البريد الإلكتروني" :paginator="$wallets">
        <x-slot:filters>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('نشطة') }}</option>
                <option value="frozen" @selected($status === 'frozen')>{{ __('مجمّدة') }}</option>
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('الرصيد') }}</th>
            <th>{{ __('نقدي') }}</th>
            <th>{{ __('ترويجي') }}</th>
            <th>{{ __('محجوز') }}</th>
            <th>{{ __('المتاح') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($wallets as $wallet)
            <tr>
                <td class="num text-muted">{{ $wallet->id }}</td>
                <td>
                    <div class="cell-media">
                        <span class="avatar">{{ mb_substr($wallet->user?->name ?? '?', 0, 1) }}</span>
                        <div>
                            <a href="{{ route('admin.wallets.show', $wallet) }}" class="cell-title d-block text-nowrap">{{ $wallet->user?->name ?? '-' }}</a>
                            <div class="cell-sub ltr text-start">{{ $wallet->user?->phone_number ?: $wallet->user?->email }}</div>
                        </div>
                    </div>
                </td>
                <td><x-admin.money :minor="$wallet->balance_minor" class="fw-bold" /></td>
                <td><x-admin.money :minor="$wallet->cash_minor" :tone="$wallet->cash_minor ? null : 'muted'" /></td>
                <td><x-admin.money :minor="$wallet->promo_minor" :tone="$wallet->promo_minor ? null : 'muted'" /></td>
                <td><x-admin.money :minor="$wallet->held_minor" :tone="$wallet->held_minor ? null : 'muted'" /></td>
                <td><x-admin.money :minor="$wallet->availableMinor()" /></td>
                <td><x-admin.status-pill domain="wallet" :status="$wallet->status" /></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.wallets.show', $wallet) }}" class="icon-btn brand" title="{{ __('فتح المحفظة') }}"><i class="bi bi-eye"></i></a>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="9" icon="wallet2" title="لا توجد محافظ"
                :text="$q !== '' ? 'لا توجد محفظة تطابق البحث.' : 'تُنشأ المحفظة عند أول شحن أو عند فتحها من هنا.'" />
        @endforelse
    </x-admin.data-table>
@endsection
