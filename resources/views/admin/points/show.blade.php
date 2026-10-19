@extends('layouts.admin')

@section('title', __('حساب النقاط #:id', ['id' => $account->id]))
@section('page-title', __('حساب النقاط #:id', ['id' => $account->id]))
@section('page-subtitle', $account->user?->name ?? 'مستخدم غير معروف')
@php
    $pageIcon = 'stars';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'النقاط' => route('admin.points.index'), '#'.$account->id => null];
    $loc = app()->getLocale() === 'en' ? 'en' : 'ar';
    $tierName = fn ($t) => $t ? ($t['name'][$loc] ?? $t['name']['ar'] ?? $t['code'] ?? '-') : '-';
    $typeLabels = ['earn' => 'كسب', 'redeem' => 'استبدال', 'expire' => 'انتهاء صلاحية', 'adjust' => 'تعديل'];
    $typeTones = ['earn' => 'success', 'redeem' => 'info', 'expire' => 'neutral', 'adjust' => 'warning'];
    $wallet = \App\Models\Wallet::where('user_id', $account->user_id)->first();
    $tz = \App\Support\TenantTime::zone($brand ?? null);
@endphp
@section('page-actions')
    @if($wallet)
        <a href="{{ route('admin.wallets.show', $wallet) }}" class="btn"><i class="bi bi-wallet2"></i> {{ __('المحفظة') }}</a>
    @endif
    <a href="{{ route('admin.points.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="الرصيد" :value="$account->balance" icon="stars" tone="points">
            {{ __('قيمتها') }} <x-admin.money :minor="$account->balance * $redeemValue" />
        </x-admin.stat-tile>
        <x-admin.stat-tile label="إجمالي المكتسب" :value="$account->lifetime_earned" icon="graph-up-arrow" tone="brand" />
        <x-admin.stat-tile label="المستوى" :value="$tierName($tier['tier'])" icon="award" tone="neutral"
            :hint="$tier['next_tier'] ? __('تبقّى :points من النقاط لبلوغ المستوى :tier', ['points' => number_format($tier['points_to_next_tier']), 'tier' => $tierName($tier['next_tier'])]) : __('أعلى مستوى')" />
        <x-admin.stat-tile label="تنتهي خلال 60 يومًا" :value="collect($expiring)->sum('points')" icon="hourglass-split" :tone="collect($expiring)->sum('points') > 0 ? 'warning' : 'neutral'" />
    </div>

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.data-table title="سجل النقاط" icon="clock-history" :count="$history->total()" :paginator="$history">
                <x-slot:head>
                    <th style="width: 56px">#</th>
                    <th>{{ __('النوع') }}</th>
                    <th>{{ __('النقاط') }}</th>
                    <th>{{ __('الرصيد بعد') }}</th>
                    <th>{{ __('ينتهي') }}</th>
                    <th>{{ __('التاريخ') }}</th>
                </x-slot:head>
                @forelse($history as $pt)
                    <tr>
                        <td class="num text-muted">{{ $pt->id }}</td>
                        <td>
                            <x-admin.status-pill size="sm" :variant="$typeTones[$pt->type] ?? 'neutral'" :label="$typeLabels[$pt->type] ?? $pt->type" />
                            @if(! empty($pt->meta['reason']))<div class="cell-sub">{{ $pt->meta['reason'] }}</div>@endif
                        </td>
                        <td class="num fw-semibold {{ $pt->points > 0 ? 'text-success' : '' }}"><span class="ltr">{{ $pt->points > 0 ? '+' : ($pt->points < 0 ? "\u{2212}" : '') }}{{ number_format(abs($pt->points)) }}</span></td>
                        <td class="num">{{ number_format($pt->balance_after) }}</td>
                        <td class="num text-muted"><span class="ltr">{{ $pt->expires_at ? $pt->expires_at->copy()->setTimezone($tz)->format('Y-m-d') : '—' }}</span>@if($pt->expires_at && $pt->remaining > 0)<div class="cell-sub">{{ __('المتبقي :points', ['points' => number_format($pt->remaining)]) }}</div>@endif</td>
                        <td class="num text-muted"><span class="ltr">{{ $pt->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i') }}</span></td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6" icon="stars" title="لا توجد حركات نقاط" />
                @endforelse
            </x-admin.data-table>
        </div>

        <div class="col-xl-4">
            @can('manage wallets')
                <x-admin.card title="تعديل يدوي" icon="plus-slash-minus" subtitle="إضافة أو خصم نقاط مع ذكر السبب">
                    <form action="{{ route('admin.points.adjust', $account) }}" method="POST"
                          data-confirm="{{ __('سيُعدَّل رصيد النقاط ويُسجَّل التعديل في سجل التدقيق. هل تريد المتابعة؟') }}"
                          data-confirm-title="{{ __('تأكيد تعديل النقاط') }}" data-confirm-ok="{{ __('تنفيذ التعديل') }}" data-confirm-tone="primary">
                        @csrf
                        <x-admin.form-field name="direction" label="النوع" type="select" :value="old('direction')"
                            :options="['credit' => 'إضافة (+)', 'debit' => 'خصم (−)']" />
                        <x-admin.form-field name="points" label="عدد النقاط" type="number" min="1" step="1" :value="old('points')" dir="ltr" required class="num" />
                        <x-admin.form-field name="reason" label="السبب" :value="old('reason')" required />
                        <button type="submit" class="btn btn-primary w-100" data-busy><i class="bi bi-check-lg"></i> {{ __('تنفيذ') }}</button>
                        <p class="form-text mt-2 mb-0">{{ __('لا تغيّر الإضافة المستوى لأنه يُحسب من النقاط المكتسبة. يبدأ الخصم بالنقاط الأقرب انتهاءً، ويُسجَّل كل تعديل في سجل التدقيق.') }}</p>
                    </form>
                </x-admin.card>
            @endcan

            <x-admin.card title="المستخدم" icon="person">
                <x-admin.detail-list :items="[
                    'الاسم' => $account->user?->name,
                    'الهاتف' => $account->user?->phone_number,
                    'البريد الإلكتروني' => $account->user?->email,
                ]" />
            </x-admin.card>
        </div>
    </div>
@endsection
