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
                    <dt>{{ __('الفعالية') }}</dt><dd>{{ $order->event ? (app()->getLocale() === 'ar' ? ($order->event->title_ar ?: $order->event->title) : $order->event->title) : '—' }} · <span class="num">{{ $order->booking_date?->format('Y-m-d') }}</span></dd>
                    <dt>{{ __('التذاكر') }}</dt>
                    <dd>
                        @foreach($order->lines as $line)
                            <div class="d-flex justify-content-between gap-3" data-line="{{ $line->key }}">
                                <span><span class="num">{{ $line->quantity }}</span> × {{ \App\Models\EventTicketType::pickLocale($line->name) ?: $line->key }}@if($line->admits > 1) <span class="text-muted small">({{ __('يدخل بها :count', ['count' => $line->admits]) }})</span>@endif</span>
                                <x-admin.money :minor="(int) $line->line_total_minor" />
                            </div>
                        @endforeach
                    </dd>
                    <dt>{{ __('المشتري') }}</dt>
                    <dd><div class="cell-title">{{ $order->buyer_name ?: ($order->buyer?->name ?? '—') }}</div><div class="cell-sub ltr text-start num">+{{ $order->buyer_phone }}</div></dd>
                    <dt>{{ __('البوابة') }}</dt><dd>{{ $order->gate ? $order->gate->code.' · '.$order->gate->localizedName() : '—' }} @if($order->device)<span class="text-muted small">· {{ $order->device->name }}</span>@endif</dd>
                    <dt>{{ __('الموظف') }}</dt><dd>{{ $order->staff?->name ?? '—' }}</dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->created_at) }}</span></dd>
                    <dt>{{ __('تنتهي صلاحية الرابط') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->expires_at) }}</span></dd>
                    <dt>{{ __('تاريخ الدفع') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->paid_at) }}</span></dd>
                    @if($order->cancelled_at)
                        <dt>{{ __('تاريخ الإلغاء') }}</dt><dd class="num"><span class="ltr">{{ $fmt($order->cancelled_at) }}</span></dd>
                    @endif
                    <dt>{{ __('الرسائل النصية') }}</dt>
                    <dd data-sms-count="{{ $order->sms_count }}"><span class="num">{{ $order->sms_count }} / {{ \App\Services\Gate\BoxOfficeService::MAX_SMS }}</span>@if($order->sms_sent_at) <span class="text-muted small">· {{ __('آخر إرسال') }} <span class="ltr">{{ $fmt($order->sms_sent_at) }}</span></span>@endif</dd>
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
                            <span class="text-muted">{{ __('لم يبدأ الزائر الدفع بعد') }}</span>
                        @endif
                    </dd>
                </dl>
            </x-admin.card>

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
                                <td class="num">{{ (int) $ticket->check_in_count }} / {{ $ticket->admits() }}</td>
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

            @if($order->status === 'paid_unfulfilled')
                <x-admin.card title="استرداد المبلغ" icon="arrow-counterclockwise">
                    <div class="alert alert-danger small d-flex gap-2 align-items-start"><i class="bi bi-exclamation-octagon-fill"></i>
                        <span>{{ __('وصل الدفع بعد انتهاء صلاحية الطلب ولم تبقَ تذاكر، فلم تصدر تذاكر. يجب استرداد المبلغ للزائر.') }}</span></div>
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
