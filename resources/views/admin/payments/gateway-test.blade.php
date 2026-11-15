{{-- Admin → Payments → Gateway test (GatewayTestController). --}}
@extends('layouts.admin')

@section('title', 'اختبار بوابة الدفع')
@section('page-title', 'اختبار بوابة الدفع')
@section('page-subtitle', 'دفعة حقيقية صغيرة عبر أموال أو ثواني، ثم التحقق منها وإلغاؤها أو استردادها من هنا')
@use('App\Support\AdminLabels')
@php
    $pageIcon = 'credit-card-2-front';
    $breadcrumbs = ['المحفظة والمدفوعات' => null, 'المدفوعات' => route('admin.payments.index'), 'اختبار بوابة الدفع' => null];
    $names = ['amwal' => 'أموال · Amwal Pay', 'thawani' => 'ثواني · Thawani Pay', 'fake' => 'بوابة محلية للتجربة'];
    $modeLabel = fn ($m) => $m === 'production' ? __('التشغيل الفعلي').' · Production' : __('تجريبية').' · UAT';
    $default = $status['amwal']['configured'] ? 'amwal' : ($status['thawani']['configured'] ? 'thawani' : ($gateways[2] ?? 'amwal'));
@endphp
@section('page-actions')
    <a href="{{ route('admin.tenants.edit', $tenant) }}#sec-gateway" class="btn"><i class="bi bi-gear"></i> {{ __('إعدادات بوابة الدفع') }}</a>
    <a href="{{ route('admin.payments.gateway-log') }}" class="btn"><i class="bi bi-hdd-network"></i> {{ __('سجل بوابة الدفع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    @if($focus)
        @php
            $tone = match ($focus->status) { 'completed' => 'success', 'pending' => 'warning', 'refunded' => 'info', default => 'danger' };
            $text = match ($focus->status) {
                'completed' => __('نجحت الدفعة التجريبية وأكّدتها البوابة.'),
                'pending' => __('لم تؤكّد البوابة الدفعة بعد. اضغط «تحقّق الآن» بعد لحظات.'),
                'refunded' => __('استُردت الدفعة التجريبية.'),
                default => __('لم تكتمل الدفعة التجريبية (:status).', ['status' => AdminLabels::label(AdminLabels::PAYMENT_STATUSES, $focus->status)]),
            };
        @endphp
        <div class="alert alert-{{ $tone }} d-flex align-items-center gap-2" data-test-result="{{ $focus->status }}">
            <i class="bi bi-{{ $focus->status === 'completed' ? 'check-circle-fill' : 'info-circle-fill' }}"></i>
            <span>{{ $text }} <span class="font-monospace small ltr">{{ $focus->transaction_id }}</span></span>
        </div>
    @endif

    <div class="row gx-4">
        <div class="col-xl-5">
            <x-admin.card title="الدفعة التجريبية" icon="lightning-charge" subtitle="تُخصم من البطاقة فعلًا؛ لا تُصدر تذاكر ولا تُحسب في الإيرادات.">
                <form action="{{ route('admin.payments.gateway-test.store') }}" method="POST" data-test="gateway-test-form">
                    @csrf
                    <div class="field">
                        <label class="form-label">{{ __('البوابة') }}</label>
                        @foreach($gateways as $g)
                            @php $ok = $g === 'fake' || $status[$g]['configured']; @endphp
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gateway" id="gw_{{ $g }}" value="{{ $g }}" @checked(old('gateway', $default) === $g) @disabled(! $ok)>
                                <label class="form-check-label" for="gw_{{ $g }}">{{ $names[$g] }}
                                    @if(in_array($g, $enabled, true))<span class="pill pill-sm pill-success"><span class="dot"></span>{{ $g === ($enabled[0] ?? null) ? __('الافتراضية للزوار') : __('مفعّلة للزوار') }}</span>@endif
                                    @if(! $ok)<span class="pill pill-sm pill-neutral">{{ __('غير مُعدّة') }}</span>@endif
                                </label>
                            </div>
                        @endforeach
                        @error('gateway')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="row">
                        <div class="col-6 field">
                            <label class="form-label" for="amount">{{ __('المبلغ') }} <span class="text-muted small">({{ $tenant->currency() }})</span></label>
                            <input type="number" name="amount" id="amount" step="0.001" min="0.1" max="5" value="{{ old('amount', '0.100') }}" class="form-control ltr @error('amount') is-invalid @enderror">
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 field">
                            <label class="form-label" for="lang">{{ __('لغة صفحة الدفع') }}</label>
                            <select name="lang" id="lang" class="form-select">
                                <option value="ar">العربية</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" data-busy><i class="bi bi-credit-card"></i> {{ __('ادفع الآن') }}</button>
                    <div class="form-text">{{ __('تُفتح صفحة الدفع الحقيقية، وتعود إلى هنا بعد الدفع. في UAT استخدم بطاقة الاختبار من أموال / ثواني.') }}</div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-xl-7">
            <x-admin.card title="البوابات" icon="hdd-network">
                <div class="small text-muted mb-3">
                    {{ __('بوابات الدفع المفعّلة للزوار الآن:') }} <b>{{ collect($enabled)->map(fn ($g) => $names[$g] ?? $g)->implode('، ') ?: '—' }}</b>.
                    {{ __('تُفعَّل بوابة الدفع وتُعطَّل في إعدادات المنظمة، وتعمل الدفعة التجريبية سواء أكانت بوابة الدفع مفعّلة للزوار أم لا؛ وتبقى كل دفعة على بوابة الدفع التي أُنشئت بها.') }}
                </div>
                @foreach(['amwal', 'thawani'] as $g)
                    @php $s = $status[$g]; @endphp
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 {{ $loop->first ? '' : 'border-top' }}" data-gateway="{{ $g }}">
                        <div>
                            <div class="fw-bold">{{ $names[$g] }}
                                @if($s['configured'])
                                    <span class="pill pill-sm {{ $s['mode'] === 'production' ? 'pill-success' : 'pill-warning' }}">{{ $modeLabel($s['mode']) }}</span>
                                @else
                                    <span class="pill pill-sm pill-neutral">{{ __('غير مُعدّة') }}</span>
                                @endif
                            </div>
                            <div class="small text-muted ltr text-start">{{ $s['details'] ?? '' }} @if($s['configured'])· {{ $s['own'] ? __('بيانات المنظمة') : __('بيانات المنصة') }}@endif</div>
                            <div class="small text-muted ltr text-start font-monospace" style="word-break: break-all">{{ $s['webhook'] }}</div>
                        </div>
                        @if($s['configured'])
                            <form action="{{ route('admin.tenants.gateway-check', $tenant) }}" method="POST">
                                @csrf
                                <button type="submit" name="gateway" value="{{ $g }}" class="btn btn-sm" data-busy><i class="bi bi-plug"></i> {{ __('اختبر الاتصال') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </x-admin.card>
        </div>
    </div>

    <x-admin.data-table title="الدفعات التجريبية" icon="list-check" :count="$payments->count()">
        <x-slot:head>
            <th>{{ __('المعاملة') }}</th>
            <th>{{ __('البوابة') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('المحاولات') }}</th>
            <th>{{ __('الاسترداد') }}</th>
            <th>{{ __('الوقت') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>
        @forelse($payments as $p)
            @php $refund = $refunds[$p->id] ?? null; $attempts = $sessions[$p->id] ?? collect(); @endphp
            <tr @class(['table-active' => $focus?->id === $p->id]) data-payment="{{ $p->transaction_id }}">
                <td><a href="{{ route('admin.payments.show', $p) }}" class="font-monospace small ltr">{{ $p->transaction_id }}</a></td>
                <td class="text-nowrap">{{ ['amwal' => 'أموال', 'thawani' => 'ثواني', 'fake' => __('محلية')][$p->gateway] ?? $p->gateway }}@if($attempts->first())<div class="small text-muted">{{ $modeLabel($attempts->first()->mode) }}</div>@endif</td>
                <td><x-admin.money :minor="(int) $p->amount_minor" :currency="$p->currency" /></td>
                <td>
                    <x-admin.status-pill domain="payment" :status="$p->status" size="sm" />
                    @if($p->needs_review)<div class="small text-warning">{{ AdminLabels::label(AdminLabels::PAYMENT_REVIEW_REASONS, $p->review_reason) }}</div>@endif
                </td>
                <td class="small">
                    @foreach($attempts->take(3) as $a)
                        <div><span class="font-monospace ltr">{{ Str::limit($a->session_id, 20) }}</span> · <x-admin.status-pill domain="gwsession" :status="$a->status" size="sm" /></div>
                    @endforeach
                </td>
                <td class="small">
                    @if($refund)
                        {!! AdminLabels::badge(AdminLabels::GATEWAY_REFUND_STATUSES, $refund->status) !!}
                        @if($refund->data['operation'] ?? null)<span class="text-muted">· {{ $refund->data['operation'] === 'void' ? __('إلغاء') : __('استرداد') }}</span>@endif
                        @if($refund->last_error)<div class="text-muted">{{ Str::limit($refund->last_error, 80) }}</div>@endif
                    @else
                        —
                    @endif
                </td>
                <td class="num text-muted small"><span class="ltr">{{ $p->created_at?->format('m-d H:i') }}</span></td>
                <td class="text-end text-nowrap">
                    @if(in_array($p->gateway, \App\Services\Payments\GatewayCheckouts::RECONCILED, true) && in_array($p->status, ['pending', 'cancelled', 'completed'], true))
                        <form action="{{ route('admin.payments.reconcile', $p) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm" data-busy title="{{ __('تحقّق من البوابة الآن') }}"><i class="bi bi-arrow-repeat"></i> {{ __('تحقّق الآن') }}</button>
                        </form>
                    @endif
                    @if($p->status === 'completed')
                        <form action="{{ route('admin.payments.refund', $p) }}" method="POST" class="d-inline" onsubmit="return confirm(@js(__('إعادة المبلغ إلى البطاقة؟')))">
                            @csrf
                            <input type="hidden" name="reason" value="Gateway test">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-busy><i class="bi bi-arrow-counterclockwise"></i> {{ __('استرداد') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="8" title="لا دفعات تجريبية بعد" />
        @endforelse
    </x-admin.data-table>
@endsection
