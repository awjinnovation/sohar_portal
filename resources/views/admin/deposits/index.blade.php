@extends('layouts.admin')

@section('title', 'التأمينات')
@section('page-title', 'إدارة التأمينات')
@section('page-subtitle', 'مبالغ محجوزة من محافظ الزوار مقابل الأكواب والأساور والمعدات')
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'safe2';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'التأمينات' => null];
@endphp

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="التأمينات المحجوزة حاليًا" :money="$summary['held_sum']" icon="safe2" tone="info"
            :href="route('admin.deposits.index', ['status' => 'held'])" hint="عرض المحجوز" />
        <x-admin.stat-tile label="عدد التأمينات المحجوزة" :value="$summary['held_count']" icon="lock" tone="brand" />
        <x-admin.stat-tile label="إجمالي المصادَر" :money="$summary['forfeited_sum']" icon="x-octagon" tone="neutral"
            :href="route('admin.deposits.index', ['status' => 'forfeited'])" hint="عرض المصادَر" />
    </div>

    <x-admin.data-table title="جميع التأمينات" icon="safe2" :count="$deposits->total()" :paginator="$deposits">
        <x-slot:filters>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach(AdminLabels::DEPOSIT_STATUSES as $sv => [$sl])
                    <option value="{{ $sv }}" @selected($filters['status'] === $sv)>{{ __($sl) }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select" aria-label="{{ __('النوع') }}">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach(\App\Models\Deposit::TYPES as $tv)
                    <option value="{{ $tv }}" @selected($filters['type'] === $tv)>{{ AdminLabels::label(AdminLabels::DEPOSIT_TYPES, $tv) }}</option>
                @endforeach
            </select>
            <select name="vendor_id" class="form-select" aria-label="{{ __('التاجر') }}">
                <option value="">{{ __('كل التجار') }}</option>
                @foreach($vendors as $v)
                    <option value="{{ $v->id }}" @selected($filters['vendor_id'] === $v->id)>{{ $v->localizedName('ar') }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('التاجر') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($deposits as $deposit)
            <tr>
                <td class="num text-muted">{{ $deposit->id }}</td>
                <td>
                    <div class="cell-title text-nowrap">{{ $deposit->user?->name ?? '-' }}</div>
                    <div class="cell-sub ltr text-start">{{ $deposit->user?->phone_number }}</div>
                </td>
                <td>
                    <div class="cell-title">{{ AdminLabels::label(AdminLabels::DEPOSIT_TYPES, $deposit->type) }}</div>
                    @if($deposit->label)<div class="cell-sub">{{ $deposit->label }}</div>@endif
                </td>
                <td><x-admin.money :minor="$deposit->amount_minor" class="fw-bold" /></td>
                <td>{{ $deposit->vendor?->localizedName('ar') ?? '-' }}</td>
                <td><x-admin.status-pill domain="deposit" :status="$deposit->status" /></td>
                <td class="num text-muted">
                    <span class="ltr">{{ $deposit->created_at?->format('Y-m-d H:i') }}</span>
                    @if($deposit->released_at)
                        <div class="cell-sub"><i class="bi bi-arrow-return-left dir-icon"></i> <span class="ltr">{{ $deposit->released_at->format('Y-m-d H:i') }}</span></div>
                    @endif
                </td>
                <td class="text-end">
                    @if($deposit->status === 'held')
                        <div class="row-actions">
                            <form action="{{ route('admin.deposits.release', $deposit) }}" method="POST"
                                  data-confirm="{{ __('إرجاع التأمين إلى رصيد :name؟', ['name' => $deposit->user?->name ?? __('المستخدم')]) }}" data-confirm-ok="{{ __('إرجاع') }}" data-confirm-tone="primary">
                                @csrf
                                <button type="submit" class="btn btn-tonal btn-sm" data-busy><i class="bi bi-arrow-return-left dir-icon"></i> {{ __('إرجاع') }}</button>
                            </form>
                            <form action="{{ route('admin.deposits.release', $deposit) }}" method="POST"
                                  data-confirm="{{ __('مصادرة التأمين؟ لن يُعاد المبلغ إلى المستخدم ولا يمكن التراجع.') }}" data-confirm-ok="{{ __('مصادرة') }}">
                                @csrf
                                <input type="hidden" name="forfeit" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm" data-busy>{{ __('مصادرة') }}</button>
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="8" icon="safe2" title="لا توجد تأمينات" text="تظهر هنا التأمينات التي يحجزها التجار من محافظ الزوار." />
        @endforelse
    </x-admin.data-table>
@endsection
