@extends('layouts.admin')

@section('title', __('الطلب :ref', ['ref' => $order->reference]))
@section('page-title', __('الطلب :ref', ['ref' => $order->reference]))
@section('page-subtitle', 'طلب من بيع البوابة')
@php
    $pageIcon = 'cart-check';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), 'مبيعات البوابات' => route('admin.gates.sales.index'), $order->reference => null];
    $tz = \App\Support\TenantTime::zone($brand ?? null);
    $statusVariant = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'neutral', 'cancelled' => 'error', 'paid_unfulfilled' => 'error', 'refunded' => 'neutral'];
    $statusLabel = ['pending' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'expired' => 'منتهي الصلاحية', 'cancelled' => 'ملغى', 'paid_unfulfilled' => 'بحاجة إلى استرداد', 'refunded' => 'مسترد'];
    $fmt = fn ($d) => $d ? $d->copy()->setTimezone($tz)->format('Y-m-d H:i') : '—';
@endphp
@section('page-actions')
    <a href="{{ route('admin.gates.sales.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row gx-4">
        <div class="col-xl-8">
            <x-admin.card title="تفاصيل طلب البيع" icon="receipt">
                <x-slot:actions><x-admin.status-pill :variant="$statusVariant[$order->status] ?? 'neutral'" :label="$statusLabel[$order->status] ?? $order->status" /></x-slot:actions>

                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 pb-3 mb-1 border-bottom">
                    <div>
                        <div class="text-muted small fw-semibold">{{ __('المبلغ') }}</div>
                        <x-admin.money :minor="(int) $order->total_minor" size="lg" />
                    </div>
                    <div class="text-end">
                        <div class="text-muted small fw-semibold">{{ __('رقم الطلب') }}</div>
                        <span class="font-monospace ltr">{{ $order->reference }}</span>
                    </div>
                </div>

                <dl class="dl">
                    <dt>{{ __('الفعالية') }}</dt><dd>{{ $order->event ? (app()->getLocale() === 'ar' ? ($order->event->title_ar ?: $order->event->title) : $order->event->title) : '—' }}@if($order->lines->isEmpty() || $order->lines->contains(fn ($l) => $l->isDated())) · <span class="num">{{ $order->booking_date?->format('Y-m-d') }}</span>@endif</dd>
                    <dt>{{ __('التذاكر') }}</dt>
                    <dd>
                        @foreach($order->lines as $line)
                            <div class="d-flex justify-content-between gap-3" data-line="{{ $line->key }}">
                                <span><span class="num">{{ $line->quantity }}</span> × {{ \App\Models\EventTicketType::pickLocale($line->name) ?: $line->key }}@if($line->admits > 1) <span class="text-muted small">({{ __('يدخل بها :count', ['count' => $line->admits]) }})</span>@endif @unless($line->isDated()) <span class="text-muted small" data-validity="{{ $line->validity }}">· {{ \App\Models\EventTicketType::validityLabel($line->validity) }}</span>@endunless</span>
                                <x-admin.money :minor="(int) $line->line_total_minor" />
                            </div>
                        @endforeach
                    </dd>
                    <dt>{{ __('المشتري') }}</dt>
                    <dd><div class="cell-title">{{ $order->buyer_name ?: ($order->buyer?->name ?? '—') }}</div><div class="cell-sub ltr text-start num">+{{ $order->buyer_phone }}</div></dd>
                    <dt>{{ __('البوابة') }}</dt><dd>{{ $order->gate ? $order->gate->code.' · '.$order->gate->localizedName() : '—' }} @if($order->device)<span class="text-muted small">· {{ $order->device->name }}</span>@endif</dd>
                    <dt>{{ __('الموظف') }}</dt><dd>{{ $order->staff?->name ?? '—' }}</dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->created_at) }}</span></dd>
                    <dt>{{ __('طريقة الدفع') }}</dt><dd data-method="{{ $order->payment_method }}"><i class="bi {{ $order->isCardTerminal() ? 'bi-credit-card' : 'bi-qr-code' }}"></i> {{ $order->isCardTerminal() ? __('جهاز البطاقة') : __('رابط الدفع') }}</dd>
                    <dt>{{ $order->isCardTerminal() ? __('تُحجز التذاكر حتى') : __('تنتهي صلاحية الرابط') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->expires_at) }}</span></dd>
                    <dt>{{ __('تاريخ الدفع') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->paid_at) }}</span></dd>
                    @if($order->cancelled_at)
                        <dt>{{ __('تاريخ الإلغاء') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->cancelled_at) }}</span></dd>
                    @endif
                    @unless($order->isCardTerminal())
                    <dt>{{ __('الرسائل النصية') }}</dt>
                    <dd data-sms-count="{{ $order->sms_count }}"><span class="num">{{ $order->sms_count }} / {{ \App\Services\Gate\BoxOfficeService::MAX_SMS }}</span>@if($order->sms_sent_at) <span class="text-muted small">· {{ __('آخر إرسال') }} <span class="ltr">{{ $fmt($order->sms_sent_at) }}</span></span>@endif</dd>
                    @endunless
                    <dt>{{ __('الدفعة') }}</dt>
                    <dd>
                        @if($order->payment)
                            @can('view payments')
                                <a href="{{ route('admin.payments.show', $order->payment) }}" class="font-monospace small ltr">{{ $order->payment->transaction_id }}</a>
                            @else
                                <span class="font-monospace small ltr">{{ $order->payment->transaction_id }}</span>
                            @endcan
                            <x-admin.status-pill domain="payment" :status="$order->payment->status" size="sm" />
                        @else
                            <span class="text-muted">{{ $order->isCardTerminal() ? __('لم يُقبل إيصال لهذا الطلب بعد') : __('لم يبدأ الزائر الدفع بعد') }}</span>
                        @endif
                    </dd>
                </dl>
            </x-admin.card>

            @if($order->isCardTerminal())
                @include('admin.gates._sales-receipts')
            @endif

            <x-admin.card title="التذاكر الصادرة" icon="ticket-perforated" :flush="true">
                <div class="x-table-wrap table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ __('رقم التذكرة') }}</th><th>{{ __('النوع') }}</th><th>{{ __('الدخول') }}</th><th>{{ __('الحالة') }}</th></tr></thead>
                        <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td class="font-monospace small ltr">
                                    @if(\Illuminate\Support\Facades\Route::has('admin.tickets.show') && auth()->user()->can('manage tickets'))
                                        <a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a>
                                    @else {{ $ticket->ticket_number }} @endif
                                </td>
                                <td>{{ $ticket->ticketType?->nameText() ?? $ticket->ticket_type }}</td>
                                <td class="num">{{ $ticket->entriesUsed() }} / {{ $ticket->admits() }}</td>
                                <td><x-admin.status-pill domain="ticket" :status="$ticket->status" /></td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="4" icon="ticket-perforated" title="لم تصدر تذاكر بعد" text="تصدر التذاكر عند اكتمال الدفع." />
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            @if($order->isCardTerminal())
            <x-admin.card title="الدفع بجهاز البطاقة" icon="credit-card">
                @if($order->needs_review)
                    <div class="alert alert-warning small d-flex gap-2 align-items-start" data-needs-review><i class="bi bi-receipt-cutoff"></i>
                        <span>{{ __('صدرت التذاكر بإيصال لم يُفحص كاملًا. راجع صورة الإيصال وبياناته، ثم سجّل المراجعة أو ألغِ العملية.') }}</span></div>
                    <form method="POST" action="{{ route('admin.gates.sales.review', $order) }}" class="mb-3">
                        @csrf
                        <input type="text" name="note" class="form-control form-control-sm mb-2" maxlength="500" placeholder="{{ __('ملاحظة (اختيارية)') }}" aria-label="{{ __('ملاحظة') }}">
                        <button type="submit" class="btn btn-success w-100" data-busy><i class="bi bi-check2-all"></i> {{ __('تمت المراجعة والإيصال سليم') }}</button>
                    </form>
                @elseif($order->isPending() || $order->status === 'expired')
                    <p class="text-muted small">{{ __('يخصم الموظف المبلغ على جهاز البطاقة ثم يصوّر الإيصال. يُعدّ الطلب مدفوعًا عند قبول الإيصال أو اعتماده هنا.') }}</p>
                @endif
                @if(in_array($order->status, ['paid', 'paid_unfulfilled'], true) && $order->payment?->method === 'pos')
                    @can('refund payments')
                        <form method="POST" action="{{ route('admin.gates.sales.void', $order) }}"
                              data-confirm="{{ __('تُلغى تذاكر هذا الطلب ويُسجَّل المبلغ مستردًّا. أعد المبلغ على جهاز البطاقة إن كان قد خُصم.') }}"
                              data-confirm-title="{{ __('إلغاء العملية') }}" data-confirm-ok="{{ __('إلغاء العملية') }}">
                            @csrf
                            <input type="text" name="reason" class="form-control form-control-sm mb-2" maxlength="500" required placeholder="{{ __('سبب الإلغاء') }}" aria-label="{{ __('سبب الإلغاء') }}">
                            <button type="submit" class="btn btn-outline-danger w-100" data-busy><i class="bi bi-x-octagon"></i> {{ __('إلغاء العملية وتذاكرها') }}</button>
                        </form>
                    @endcan
                @endif
            </x-admin.card>
            @else
            <x-admin.card title="رابط الدفع" icon="qr-code">
                <div class="bg-white rounded border p-3 mx-auto mb-3" style="max-width: 220px{{ $order->isPending() ? '' : '; opacity: .4' }}">
                    {!! QrCode::size(190)->margin(0)->generate($paymentUrl) !!}
                </div>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control font-monospace ltr" value="{{ $paymentUrl }}" readonly id="payLink" aria-label="{{ __('رابط الدفع') }}">
                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('payLink').value)"><i class="bi bi-clipboard"></i></button>
                </div>
                <a href="{{ $paymentUrl }}" target="_blank" rel="noopener" class="btn btn-tonal w-100"><i class="bi bi-box-arrow-up-right"></i> {{ __('فتح صفحة الدفع') }}</a>
            </x-admin.card>
            @endif

            @if($order->status === 'paid_unfulfilled')
                <x-admin.card title="استرداد المبلغ" icon="arrow-counterclockwise">
                    <div class="alert alert-danger small d-flex gap-2 align-items-start"><i class="bi bi-exclamation-octagon-fill"></i>
                        <span>{{ $order->isCardTerminal() ? __('وصل إيصال جهاز البطاقة بعد انتهاء صلاحية الطلب ولم تبقَ تذاكر، فلم تصدر تذاكر. أعد المبلغ للزائر على جهاز البطاقة.') : __('وصل الدفع بعد انتهاء صلاحية الطلب ولم تبقَ تذاكر، فلم تصدر تذاكر. يجب استرداد المبلغ للزائر.') }}</span></div>
                    @can('refund payments')
                        <form method="POST" action="{{ route('admin.gates.sales.refund', $order) }}"
                              data-confirm="{{ __('تُسجَّل الدفعة مستردة، ثم تكمل الاسترداد عبر بوابة الدفع.') }}" data-confirm-title="{{ __('تأكيد الاسترداد') }}" data-confirm-ok="{{ __('استرداد') }}">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100" data-busy><i class="bi bi-arrow-counterclockwise"></i> {{ __('استرداد الدفعة') }}</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">{{ __('يسترد المبلغ من يملك صلاحية استرداد المدفوعات.') }}</p>
                    @endcan
                </x-admin.card>
            @endif

            @if($order->isPending())
                <x-admin.card title="إلغاء الطلب" icon="x-octagon">
                    <p class="text-muted small">{{ __('يُلغى رابط الدفع وتعود التذاكر المحجوزة إلى المتاح للبيع.') }}</p>
                    <form method="POST" action="{{ route('admin.gates.sales.cancel', $order) }}"
                          data-confirm="{{ __('لن يتمكن الزائر من الدفع عبر هذا الرابط بعد إلغائه.') }}" data-confirm-title="{{ __('تأكيد إلغاء الطلب') }}" data-confirm-ok="{{ __('إلغاء الطلب') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100" data-busy><i class="bi bi-x-lg"></i> {{ __('إلغاء الطلب') }}</button>
                    </form>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
