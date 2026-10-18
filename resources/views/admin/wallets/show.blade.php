@extends('layouts.admin')

@section('title', __('المحفظة #:id', ['id' => $wallet->id]))
@section('page-title', __('المحفظة #:id', ['id' => $wallet->id]))
@section('page-subtitle', $wallet->user?->name ?? 'مستخدم غير معروف')
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'wallet2';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المحافظ' => route('admin.wallets.index'), __('المحفظة #:id', ['id' => $wallet->id]) => null];
    $cur = $brand?->currency() ?? $wallet->currency;
    $frozen = $wallet->isFrozen();
    $heldCount = $deposits->where('status', 'held')->count();
    $bucketVariant = ['cash' => 'neutral', 'promo' => 'accent', 'mixed' => 'info'];
    $pointsLabel = $brand?->setting('points.label.'.app()->getLocale()) ?: 'النقاط';
@endphp
@section('page-actions')
    <a href="{{ route('admin.wallets.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@push('styles')
<style>
    .wallet-card.is-frozen { filter: saturate(.35); }
    .wallet-card .wc-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .wallet-card .wc-name { font-weight: 700; font-size: 16px; line-height: 1.4; }
    .wallet-card .money-hero { display: block; margin-top: 2px; }
    .ledger-ico { width: 34px; height: 34px; font-size: 15px; }
    .ledger-refund { width: 290px; }
    .ledger-note { max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
@endpush

@section('content')
    @include('admin.partials.alerts')

    <div class="row gx-4">
        <div class="col-xl-8">
            {{-- Hero: balance + buckets --}}
            <div class="wallet-card {{ $frozen ? 'is-frozen' : '' }} mb-4" aria-label="{{ __('رصيد المحفظة') }}">
                <div class="wc-head">
                    <span class="avatar">{{ mb_substr($wallet->user?->name ?? '?', 0, 1) }}</span>
                    <div class="flex-grow-1">
                        <div class="wc-name">{{ $wallet->user?->name ?? '-' }}</div>
                        <div class="wc-label ltr text-start">{{ collect([$wallet->user?->phone_number, $wallet->user?->email])->filter()->implode(' · ') }}</div>
                    </div>
                    @if($frozen)
                        <span class="wc-chip"><i class="bi bi-lock-fill"></i> {{ __('المحفظة مجمّدة') }}</span>
                    @else
                        <span class="wc-chip"><i class="bi bi-check-circle-fill"></i> {{ __('نشطة') }}</span>
                    @endif
                </div>

                <div class="wc-label mt-4">{{ __('الرصيد الإجمالي') }}</div>
                <x-admin.money :minor="$wallet->balance_minor" size="hero" />

                <div class="wc-row">
                    <span class="wc-chip"><i class="bi bi-cash-stack"></i> {{ __('نقدي') }} <x-admin.money :minor="$wallet->cash_minor" /></span>
                    <span class="wc-chip"><i class="bi bi-gift"></i> {{ __('ترويجي (مكافأة)') }} <x-admin.money :minor="$wallet->promo_minor" /></span>
                    <span class="wc-chip"><i class="bi bi-safe2"></i> {{ __('محجوز (تأمينات)') }} <x-admin.money :minor="$wallet->held_minor" /></span>
                    <span class="wc-chip"><i class="bi bi-check2-circle"></i> {{ __('المتاح') }} <x-admin.money :minor="$wallet->availableMinor()" /></span>
                </div>
            </div>

            <x-admin.card title="تفصيل الرصيد" icon="pie-chart" :subtitle="__('جميع المبالغ بعملة :currency — الرصيد النقدي والترويجي والمحجوز منفصلة دائمًا', ['currency' => $cur])">
                <div class="bucket-grid">
                    <div class="bucket">
                        <div class="b-label"><span class="sw" style="background: var(--success)"></span> {{ __('نقدي') }}</div>
                        <x-admin.money :minor="$wallet->cash_minor" />
                        <div class="cell-sub">{{ __('من الشحن والمبالغ المستردة') }}</div>
                    </div>
                    <div class="bucket">
                        <div class="b-label"><span class="sw" style="background: var(--accent)"></span> {{ __('ترويجي (مكافأة)') }}</div>
                        <x-admin.money :minor="$wallet->promo_minor" />
                        <div class="cell-sub">{{ __('غير قابل للاسترداد') }}</div>
                    </div>
                    <div class="bucket">
                        <div class="b-label"><span class="sw" style="background: var(--info)"></span> {{ __('محجوز (تأمينات)') }}</div>
                        <x-admin.money :minor="$wallet->held_minor" />
                        <div class="cell-sub">{{ $heldCount ? __(':count تأمين محجوز', ['count' => $heldCount]) : __('لا توجد تأمينات محجوزة') }}</div>
                    </div>
                    <div class="bucket">
                        <div class="b-label"><span class="sw" style="background: var(--warning-fill)"></span> {{ __('قابل للاسترداد') }}</div>
                        <x-admin.money :minor="$wallet->refundableMinor()" />
                        <div class="cell-sub">{{ __('النقدي ناقص المحجوز') }}</div>
                    </div>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            {{-- Manual adjustment --}}
            <x-admin.card title="تعديل يدوي" icon="plus-slash-minus" subtitle="إضافة أو خصم مبلغ من رصيد محدد">
                <form action="{{ route('admin.wallets.adjust', $wallet) }}" method="POST" id="adjustForm"
                      data-confirm="{{ __('سيتم تنفيذ التعديل على رصيد المحفظة وتسجيله في سجل التدقيق. هل تريد المتابعة؟') }}"
                      data-confirm-title="{{ __('تأكيد تعديل الرصيد') }}" data-confirm-ok="{{ __('تنفيذ التعديل') }}" data-confirm-tone="primary">
                    @csrf
                    <div class="row g-2">
                        <x-admin.form-field col="col-6" name="direction" label="النوع" type="select" :value="old('direction')"
                            :options="['credit' => 'إضافة (+)', 'debit' => 'خصم (−)']" />
                        <x-admin.form-field col="col-6" name="bucket" label="الرصيد" type="select" :value="old('bucket', 'promo')"
                            :options="['promo' => 'ترويجي (مكافأة)', 'cash' => 'نقدي']" />
                    </div>
                    <x-admin.form-field name="amount" :label="__('المبلغ (:currency)', ['currency' => $cur])" :value="old('amount')" placeholder="1.500" dir="ltr" inputmode="decimal" required class="num" />
                    <x-admin.form-field name="reason" label="السبب" :value="old('reason')" required />
                    <button type="submit" class="btn btn-primary w-100" data-busy><i class="bi bi-check-lg"></i> {{ __('تنفيذ') }}</button>
                    <p class="form-text mt-2 mb-0">{{ __('الرصيد الترويجي غير قابل للاسترداد. الخصم من الرصيد الترويجي يبدأ بالترويجي ثم النقدي. يتم تسجيل كل تعديل في سجل التدقيق.') }}</p>
                </form>
            </x-admin.card>

            {{-- Freeze / unfreeze --}}
            <x-admin.card title="حالة المحفظة" icon="shield-lock">
                <x-slot:actions><x-admin.status-pill domain="wallet" :status="$wallet->status" /></x-slot:actions>
                @if($frozen)
                    <form action="{{ route('admin.wallets.status', $wallet) }}" method="POST"
                          data-confirm="{{ __('سيتم إلغاء تجميد المحفظة ليتمكن المستخدم من الدفع والشحن مجددًا.') }}"
                          data-confirm-title="{{ __('إلغاء تجميد المحفظة') }}" data-confirm-ok="{{ __('إلغاء التجميد') }}" data-confirm-tone="primary">
                        @csrf
                        <input type="hidden" name="status" value="active">
                        <p class="text-muted small mb-2">{{ __('المحفظة مجمّدة حاليًا؛ لا يمكن للمستخدم الدفع أو الشحن.') }}</p>
                        <div class="field">
                            <input type="text" name="reason" class="form-control" placeholder="{{ __('سبب إلغاء التجميد (اختياري)') }}" aria-label="{{ __('سبب إلغاء التجميد') }}">
                        </div>
                        <button type="submit" class="btn btn-success w-100" data-busy><i class="bi bi-unlock"></i> {{ __('إلغاء التجميد') }}</button>
                    </form>
                @else
                    <form action="{{ route('admin.wallets.status', $wallet) }}" method="POST"
                          data-confirm="{{ __('سيتم تجميد المحفظة ومنع المستخدم من الدفع والشحن حتى إلغاء التجميد.') }}"
                          data-confirm-title="{{ __('تجميد المحفظة') }}" data-confirm-ok="{{ __('تجميد') }}">
                        @csrf
                        <input type="hidden" name="status" value="frozen">
                        <p class="text-muted small mb-2">{{ __('التجميد يوقف الدفع والشحن مؤقتًا دون المساس بالرصيد.') }}</p>
                        <div class="field">
                            <input type="text" name="reason" class="form-control" placeholder="{{ __('سبب التجميد (اختياري)') }}" aria-label="{{ __('سبب التجميد') }}">
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100" data-busy><i class="bi bi-lock"></i> {{ __('تجميد المحفظة') }}</button>
                    </form>
                @endif
            </x-admin.card>
        </div>
    </div>

    {{-- Ledger --}}
    <x-admin.data-table title="سجل العمليات" icon="journal-text" :count="$transactions->total()" :paginator="$transactions">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('العملية') }}</th>
            <th>{{ __('الرصيد') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('الرصيد بعد') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($transactions as $tx)
            @php
                $txBucket = $tx->bucket();
                $txCredit = $tx->direction === 'credit';
                $txNote = collect([$tx->vendor?->localizedName(app()->getLocale()), $tx->meta['label'] ?? null, $tx->meta['reason'] ?? null])->filter()->implode(' · ');
            @endphp
            <tr>
                <td class="num text-muted">{{ $tx->id }}</td>
                <td>
                    <div class="cell-media">
                        <span class="tx-ico ledger-ico {{ $txCredit ? 'tone-success' : 'tone-neutral' }}">
                            <i class="bi {{ $txCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}"></i>
                        </span>
                        <div>
                            <div class="cell-title text-nowrap">{{ AdminLabels::label(AdminLabels::TX_TYPES, $tx->type) }}</div>
                            @if($txNote !== '')<div class="cell-sub ledger-note" title="{{ $txNote }}">{{ $txNote }}</div>@endif
                        </div>
                    </div>
                </td>
                <td><x-admin.status-pill size="sm" :variant="$bucketVariant[$txBucket] ?? 'neutral'" :label="AdminLabels::label(AdminLabels::BUCKETS, $txBucket)" /></td>
                <td>
                    <x-admin.money :minor="$tx->amount_minor" :direction="$tx->direction" />
                    @if($txBucket === 'mixed')
                        <div class="cell-sub">{{ __('نقدي') }} <x-admin.money :minor="$tx->cash_delta_minor" :signed="true" tone="muted" /> · {{ __('ترويجي') }} <x-admin.money :minor="$tx->promo_delta_minor" :signed="true" tone="muted" /></div>
                    @endif
                </td>
                <td><x-admin.money :minor="$tx->balance_after_minor" tone="muted" /></td>
                <td><x-admin.status-pill domain="tx" :status="$tx->status" /></td>
                <td class="num text-muted"><span class="ltr">{{ $tx->created_at?->format('Y-m-d H:i') }}</span></td>
                <td class="text-end">
                    @if($tx->type === 'vendor_payment' && $tx->direction === 'debit')
                        @if(in_array($tx->id, $refundedChargeIds, true))
                            <x-admin.status-pill variant="neutral" label="مسترد" icon="arrow-counterclockwise" />
                        @else
                            <div class="dropdown d-inline-block">
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                                    <i class="bi bi-arrow-counterclockwise"></i> {{ __('استرداد') }}
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3 ledger-refund">
                                    <form action="{{ route('admin.wallets.refund-charge', $tx) }}" method="POST"
                                          data-confirm="{{ __('سيتم إرجاع مبلغ هذه العملية إلى محفظة المستخدم.') }}" data-confirm-title="{{ __('استرداد عملية الدفع') }}"
                                          data-confirm-ok="{{ __('استرداد') }}" data-confirm-tone="primary">
                                        @csrf
                                        <div class="fw-bold mb-1">{{ __('استرداد عملية الدفع') }}</div>
                                        <div class="cell-sub mb-2">{{ __('يُعاد') }} <x-admin.money :minor="$tx->amount_minor" /> {{ __('إلى الرصيد النقدي.') }}</div>
                                        <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('السبب (اختياري)') }}" aria-label="{{ __('سبب الاسترداد') }}">
                                        <button type="submit" class="btn btn-primary btn-sm w-100" data-busy><i class="bi bi-arrow-counterclockwise"></i> {{ __('تأكيد الاسترداد') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="8" icon="journal-text" title="لا توجد عمليات" text="ستظهر هنا عمليات الشحن والدفع والتأمينات." />
        @endforelse
    </x-admin.data-table>

    {{-- Deposits --}}
        <x-admin.card title="التأمينات" icon="safe2" :subtitle="$heldCount ? __(':count محجوز حاليًا', ['count' => $heldCount]) : null" :flush="true">
            @if($deposits->isEmpty())
                <x-admin.empty-state icon="safe2" title="لا توجد تأمينات" text="لم يُحجز أي تأمين من هذه المحفظة." />
            @else
            <div class="x-table-wrap">
                <table class="table align-middle">
                    <thead><tr><th>#</th><th>{{ __('النوع') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('التاجر') }}</th><th>{{ __('الحالة') }}</th><th class="text-end"></th></tr></thead>
                    <tbody>
                        @foreach($deposits as $deposit)
                            <tr>
                                <td class="num text-muted">{{ $deposit->id }}</td>
                                <td>
                                    <div class="cell-title">{{ AdminLabels::label(AdminLabels::DEPOSIT_TYPES, $deposit->type) }}</div>
                                    @if($deposit->label)<div class="cell-sub">{{ $deposit->label }}</div>@endif
                                </td>
                                <td><x-admin.money :minor="$deposit->amount_minor" /></td>
                                <td class="nowrap">{{ $deposit->vendor?->localizedName(app()->getLocale()) ?? '-' }}</td>
                                <td><x-admin.status-pill domain="deposit" :status="$deposit->status" /></td>
                                <td class="text-end">
                                    @can('manage deposits')
                                        @if($deposit->status === 'held')
                                            <form action="{{ route('admin.deposits.release', $deposit) }}" method="POST" class="d-inline"
                                                  data-confirm="{{ __('سيتم إرجاع مبلغ التأمين إلى رصيد المستخدم.') }}" data-confirm-ok="{{ __('إرجاع') }}" data-confirm-tone="primary">
                                                @csrf
                                                <button type="submit" class="btn btn-tonal btn-sm" data-busy><i class="bi bi-arrow-return-left dir-icon"></i> {{ __('إرجاع') }}</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </x-admin.card>

    {{-- Refund requests --}}
        <x-admin.card title="طلبات الاسترداد" icon="arrow-counterclockwise" :flush="true">
            @if($refundRequests->isEmpty())
                <x-admin.empty-state icon="arrow-counterclockwise" title="لا توجد طلبات" text="لم يطلب المستخدم استرداد رصيده." />
            @else
            <div class="x-table-wrap">
                <table class="table align-middle">
                    <thead><tr><th>#</th><th>{{ __('المبلغ') }}</th><th>{{ __('الطريقة') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('التاريخ') }}</th><th class="text-end"></th></tr></thead>
                    <tbody>
                        @foreach($refundRequests as $rr)
                            <tr>
                                <td class="num text-muted">{{ $rr->id }}</td>
                                <td><x-admin.money :minor="$rr->amount_minor" /></td>
                                <td>{{ AdminLabels::label(AdminLabels::REFUND_METHODS, $rr->method) }}</td>
                                <td><x-admin.status-pill domain="refund" :status="$rr->status" /></td>
                                <td class="num text-muted"><span class="ltr">{{ $rr->created_at?->format('Y-m-d') }}</span></td>
                                <td class="text-end">
                                    @can('manage refunds')
                                        <a href="{{ route('admin.refund-requests.show', $rr) }}" class="icon-btn brand" title="{{ __('عرض الطلب') }}"><i class="bi bi-eye"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </x-admin.card>

    {{-- Points --}}
    <x-admin.card :title="$pointsLabel" icon="stars" :flush="true">
        @if($pointsAccount)
            <div class="p-3 pb-0">
                <div class="kv-grid mb-3">
                    <div class="kv"><div class="k">{{ __('الرصيد') }}</div><div class="v num">{{ number_format($pointsAccount->balance) }}</div></div>
                    <div class="kv"><div class="k">{{ __('إجمالي المكتسب') }}</div><div class="v num">{{ number_format($pointsAccount->lifetime_earned) }}</div></div>
                    <div class="kv"><div class="k">{{ __('المستوى') }}</div><div class="v">{{ $tier['tier']['name'][app()->getLocale()] ?? $tier['tier']['name']['ar'] ?? ($tier['tier']['code'] ?? '-') }}</div></div>
                    <div class="kv"><div class="k">{{ __('للمستوى التالي') }}</div><div class="v num">{{ $tier['next_tier'] ? number_format($tier['points_to_next_tier']) : '-' }}</div></div>
                </div>
            </div>
            <div class="x-table-wrap">
                <table class="table align-middle">
                    <thead><tr><th>#</th><th>{{ __('النوع') }}</th><th>{{ __('النقاط') }}</th><th>{{ __('الرصيد بعد') }}</th><th>{{ __('ينتهي') }}</th><th>{{ __('التاريخ') }}</th></tr></thead>
                    <tbody>
                        @forelse($pointsTransactions as $pt)
                            <tr>
                                <td class="num text-muted">{{ $pt->id }}</td>
                                <td>{{ $pt->type }}</td>
                                <td class="num fw-semibold {{ $pt->points > 0 ? 'text-success' : '' }}"><span class="ltr">{{ $pt->points > 0 ? '+' : ($pt->points < 0 ? "\u{2212}" : '') }}{{ number_format(abs($pt->points)) }}</span></td>
                                <td class="num">{{ number_format($pt->balance_after) }}</td>
                                <td class="num text-muted"><span class="ltr">{{ $pt->expires_at?->format('Y-m-d') ?? '-' }}</span></td>
                                <td class="num text-muted"><span class="ltr">{{ $pt->created_at?->format('Y-m-d H:i') }}</span></td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="6" icon="stars" title="لا توجد حركات نقاط" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty-state icon="stars" title="لا يوجد حساب نقاط" text="لا يوجد حساب نقاط لهذا المستخدم بعد." />
        @endif
    </x-admin.card>
@endsection
