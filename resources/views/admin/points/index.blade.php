@extends('layouts.admin')

@section('title', 'النقاط')
@section('page-title', 'نقاط الولاء')
@section('page-subtitle', 'أرصدة النقاط والمستويات وقواعد الكسب والاستبدال')
@php
    $pageIcon = 'stars';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'النقاط' => null];
    $loc = app()->getLocale() === 'en' ? 'en' : 'ar';
    $pointsLabel = $rules['label'][$loc] ?? __('النقاط');
    $tierName = fn ($t) => $t ? ($t['name'][$loc] ?? $t['name']['ar'] ?? $t['code'] ?? '-') : '-';
@endphp
@section('page-actions')
    @can('manage settings')
        <a href="{{ route('admin.branding.edit') }}#sec-points" class="btn"><i class="bi bi-sliders"></i> {{ __('تعديل القواعد') }}</a>
    @endcan
@endsection

@section('content')
    @include('admin.partials.alerts')

    @unless($moduleEnabled)
        <div class="alert alert-warning d-flex gap-2 align-items-center"><i class="bi bi-exclamation-triangle-fill"></i>{{ __('وحدة النقاط معطلة لهذه المنظمة — لا تُكتسب نقاط جديدة.') }}</div>
    @endunless

    <div class="stat-grid">
        <x-admin.stat-tile label="حسابات النقاط" :value="$totals['accounts']" icon="people" tone="neutral" />
        <x-admin.stat-tile label="النقاط القائمة" :value="$totals['outstanding']" icon="stars" tone="points">
            {{ __('قيمتها') }} <x-admin.money :minor="$totals['liability_minor']" />
        </x-admin.stat-tile>
        <x-admin.stat-tile label="إجمالي المكتسب" :value="$totals['lifetime']" icon="graph-up-arrow" tone="brand" />
        <x-admin.stat-tile label="تنتهي خلال 30 يومًا" :value="$totals['expiring_30d']" icon="hourglass-split" :tone="$totals['expiring_30d'] > 0 ? 'warning' : 'neutral'" />
    </div>

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.data-table title="حسابات النقاط" icon="stars" :count="$accounts->total()" search="q"
                search-placeholder="ابحث بالاسم أو رقم الهاتف أو البريد الإلكتروني" :paginator="$accounts">
                <x-slot:filters>
                    <select name="tier" class="form-select" aria-label="{{ __('المستوى') }}">
                        <option value="">{{ __('كل المستويات') }}</option>
                        @foreach($tiers as $t)
                            <option value="{{ $t['code'] }}" @selected($tierCode === $t['code'])>{{ $tierName($t) }}</option>
                        @endforeach
                    </select>
                </x-slot:filters>
                <x-slot:head>
                    <th style="width: 56px">#</th>
                    <th>{{ __('المستخدم') }}</th>
                    <th>{{ __('الرصيد') }}</th>
                    <th>{{ __('المستوى') }}</th>
                    <th>{{ __('إجمالي المكتسب') }}</th>
                    <th class="text-end">{{ __('الإجراءات') }}</th>
                </x-slot:head>

                @forelse($accounts as $account)
                    @php $ti = $tierOf($account); @endphp
                    <tr>
                        <td class="num text-muted">{{ $account->id }}</td>
                        <td>
                            <div class="cell-media">
                                <span class="avatar">{{ mb_substr($account->user?->name ?? '?', 0, 1) }}</span>
                                <div>
                                    <a href="{{ route('admin.points.show', $account) }}" class="cell-title d-block text-nowrap">{{ $account->user?->name ?? '-' }}</a>
                                    <div class="cell-sub ltr text-start">{{ $account->user?->phone_number ?: $account->user?->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="num fw-bold">{{ number_format($account->balance) }}</td>
                        <td>
                            @if($ti['tier'])
                                <span class="pill pill-sm pill-neutral"><span class="dot" style="background: {{ $ti['tier']['color'] ?? 'currentColor' }}"></span>{{ $tierName($ti['tier']) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">{{ number_format($account->lifetime_earned) }}</td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="{{ route('admin.points.show', $account) }}" class="icon-btn brand" title="{{ __('السجل') }}"><i class="bi bi-clock-history"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6" icon="stars" title="لا توجد حسابات نقاط"
                        :text="$q !== '' ? 'لا يوجد حساب يطابق البحث.' : 'يُنشأ حساب النقاط عند أول عملية كسب للمستخدم.'" />
                @endforelse
            </x-admin.data-table>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="قواعد النقاط" icon="sliders" :subtitle="$pointsLabel">
                <x-admin.detail-list :items="[
                    'الكسب لكل وحدة عملة' => rtrim(rtrim(number_format((float) ($rules['earn_per_currency_unit'] ?? 0), 2, '.', ''), '0'), '.').' / 1 '.($brand?->currency() ?? ''),
                    'قيمة النقطة عند الاستبدال' => ($brand ? $brand->formatMoney((int) ($rules['redeem_value_minor_per_point'] ?? 0)).' '.$brand->currency() : (int) ($rules['redeem_value_minor_per_point'] ?? 0)),
                    'أقل عدد للاستبدال' => number_format((int) ($rules['min_redeem_points'] ?? 0)),
                    'الصلاحية' => (int) ($rules['expiry_days'] ?? 0) > 0 ? __(':days يومًا', ['days' => (int) $rules['expiry_days']]) : __('لا تنتهي'),
                    'نقاط عند الدخول' => ! empty($rules['earn_on_checkin']) ? number_format((int) ($rules['checkin_points'] ?? 0)) : __('معطّل'),
                ]" />
                @if($tiers->isNotEmpty())
                    <h3 class="h6 fw-bold mt-3 mb-2">{{ __('المستويات') }}</h3>
                    <ul class="list-unstyled m-0 d-grid gap-2">
                        @foreach($tiers as $t)
                            <li class="d-flex align-items-center gap-2">
                                <span class="dot d-inline-block rounded-circle" style="width:10px;height:10px;background: {{ $t['color'] ?? '#999' }}"></span>
                                <span class="fw-semibold">{{ $tierName($t) }}</span>
                                <span class="text-muted small num ms-auto">{{ __('من :points', ['points' => number_format((int) ($t['min_points'] ?? 0))]) }} · ×{{ $t['multiplier'] ?? 1 }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="form-text mt-2 mb-0">{{ __('المستوى يُحسب من إجمالي النقاط المكتسبة.') }}</p>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
