@extends('layouts.admin')

{{-- Search by phone number (Admin\PhoneLookupController): the visitor's account, tickets, payments and gate orders in this organisation. --}}
@section('title', 'البحث برقم الهاتف')
@section('page-title', 'البحث برقم الهاتف')
@section('page-subtitle', 'تذاكر الزائر ومدفوعاته وطلبات البوابات في هذه المنظمة')
@php
    use App\Support\AdminLabels;
    $pageIcon = 'telephone';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'البحث برقم الهاتف' => null];
    $tz = \App\Support\TenantTime::zone($brand ?? null);
    $at = fn ($date) => $date?->copy()->setTimezone($tz)->format('Y-m-d H:i');
    $lang = app()->getLocale() === 'en' ? 'en' : 'ar';
    $orderVariant = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'neutral', 'cancelled' => 'error', 'paid_unfulfilled' => 'error', 'refunded' => 'neutral'];
    $orderLabel = ['pending' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'expired' => 'منتهي الصلاحية', 'cancelled' => 'ملغى', 'paid_unfulfilled' => 'بحاجة إلى استرداد', 'refunded' => 'مسترد'];
    $nothing = $searched && $accounts->isEmpty() && $tickets->isEmpty() && $payments->isEmpty() && $orders->isEmpty();
@endphp

@section('content')
    @include('admin.partials.alerts')

    <x-admin.card title="رقم هاتف الزائر" icon="search" subtitle="أدخل الرقم كاملًا بأي صيغة، أو 4 أرقام منه على الأقل">
        <form method="GET" action="{{ route('admin.phone-lookup') }}" class="d-flex flex-wrap gap-2 align-items-start" role="search" data-phone-lookup>
            <div style="flex: 1 1 260px; max-width: 420px">
                <input type="tel" name="phone" value="{{ $phone }}" class="form-control @if($tooShort) is-invalid @endif" dir="ltr" inputmode="tel"
                       placeholder="9XXXXXXX" aria-label="{{ __('رقم الهاتف') }}" autocomplete="off" autofocus>
                @if($tooShort)
                    <div class="invalid-feedback d-block">{{ __('أدخل رقم الهاتف كاملًا، أو 4 أرقام منه على الأقل') }}</div>
                @endif
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> {{ __('بحث') }}</button>
            @if($phone !== '')
                <a href="{{ route('admin.phone-lookup') }}" class="btn btn-secondary">{{ __('مسح') }}</a>
            @endif
        </form>
        @if($searched)
            <p class="text-muted small mt-2 mb-0" data-lookup-mode="{{ $exact ? 'exact' : 'partial' }}">
                @if($exact)
                    <i class="bi bi-check2-circle"></i> {{ __('الرقم المطابق:') }} <span class="ltr num">{{ $exact }}</span>
                @else
                    <i class="bi bi-info-circle"></i> {{ __('بحث جزئي: الأرقام التي تحتوي على :digits', ['digits' => preg_replace('/\D/', '', $phone)]) }}
                @endif
            </p>
        @endif
    </x-admin.card>

    @if($nothing)
        <x-admin.card>
            <x-admin.empty-state icon="telephone-x" title="لا توجد نتائج لهذا الرقم"
                text="لا توجد تذاكر أو مدفوعات أو طلبات بوابات لهذا الرقم في هذه المنظمة. تأكد من الرقم أو جرّب جزءًا منه." />
        </x-admin.card>
    @elseif($searched)
        <div class="stat-grid">
            @if($can['tickets'])
                <x-admin.stat-tile label="التذاكر" :value="$totals['tickets']" icon="ticket-perforated" tone="brand" />
            @endif
            @if($can['payments'])
                <x-admin.stat-tile label="المدفوعات" :value="$totals['payments']" icon="credit-card-2-front" tone="info" />
                <x-admin.stat-tile label="إجمالي المدفوع" :money="$totals['paid_minor']" icon="cash-coin" tone="success" hint="المدفوعات المكتملة" />
            @endif
            @if($can['orders'])
                <x-admin.stat-tile label="طلبات البوابات" :value="$totals['orders']" icon="cart-check" tone="warning" />
            @endif
        </div>

        @if($accounts->isNotEmpty())
            <x-admin.card title="الحسابات" icon="person-vcard" :flush="true" data-lookup-accounts="{{ $accounts->count() }}">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('الاسم') }}</th><th>{{ __('رقم الهاتف') }}</th><th>{{ __('البريد الإلكتروني') }}</th>
                            @if($can['tickets'])<th class="text-end">{{ __('التذاكر') }}</th>@endif
                            @if($can['payments'])<th class="text-end">{{ __('المدفوعات') }}</th>@endif
                            @if($can['orders'])<th class="text-end">{{ __('طلبات البوابات') }}</th>@endif
                            <th>{{ __('تاريخ التسجيل') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach($accounts as $row)
                                <tr>
                                    <td class="cell-title">{{ $row['user']->name }}</td>
                                    <td><span class="ltr num">{{ $row['user']->phone_number ?? '—' }}</span></td>
                                    <td><span class="ltr">{{ $row['user']->email ?? '—' }}</span></td>
                                    @if($can['tickets'])<td class="text-end num">{{ $row['tickets'] }}</td>@endif
                                    @if($can['payments'])<td class="text-end num">{{ $row['payments'] }}</td>@endif
                                    @if($can['orders'])<td class="text-end num">{{ $row['orders'] }}</td>@endif
                                    <td class="num">{{ $row['user']->created_at?->format('Y-m-d') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        @endif

        @if($can['tickets'])
            <x-admin.data-table title="التذاكر" icon="ticket-perforated" :count="$totals['tickets']" data-lookup-tickets="{{ $tickets->count() }}">
                <x-slot:head>
                    <th>{{ __('رقم التذكرة') }}</th><th>{{ __('الفعالية') }}</th><th>{{ __('تاريخ الزيارة') }}</th>
                    <th>{{ __('الحالة') }}</th><th>{{ __('تاريخ الشراء') }}</th><th class="text-end">{{ __('الإجراءات') }}</th>
                </x-slot:head>
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="nowrap"><a href="{{ route('admin.tickets.show', $ticket) }}" class="num fw-semibold" dir="ltr">{{ $ticket->ticket_number }}</a></td>
                        <td>
                            <div class="cell-title">{{ $ticket->event?->title_ar ?: ($ticket->event?->title ?? '—') }}</div>
                            <div class="cell-sub">{{ $ticket->ticketType ? $ticket->ticketType->nameText($lang) : $ticket->ticket_type }}</div>
                        </td>
                        <td class="num">@if($ticket->booking_date){{ $ticket->booking_date->format('Y-m-d') }}@else<span class="cell-sub">{{ \App\Models\EventTicketType::validityLabel($ticket->validityValue()) }}</span>@endif</td>
                        <td><x-admin.status-pill domain="ticket" :status="$ticket->status" /></td>
                        <td class="num">{{ $at($ticket->purchase_date ?? $ticket->created_at) }}</td>
                        <td class="text-end"><a href="{{ route('admin.tickets.show', $ticket) }}" class="icon-btn" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6" icon="ticket-perforated" title="لا توجد تذاكر" text="لا توجد تذاكر لهذا الرقم في هذه المنظمة." />
                @endforelse
            </x-admin.data-table>
            @if($totals['tickets'] > $tickets->count())
                <p class="text-muted small">{{ __('المعروض هنا أحدث :shown من أصل :total.', ['shown' => $tickets->count(), 'total' => number_format($totals['tickets'])]) }}</p>
            @endif
        @endif

        @if($can['payments'])
            <x-admin.data-table title="المدفوعات" icon="credit-card-2-front" :count="$totals['payments']" data-lookup-payments="{{ $payments->count() }}">
                <x-slot:head>
                    <th>{{ __('رقم المعاملة') }}</th><th>{{ __('النوع') }}</th><th>{{ __('طريقة الدفع') }}</th>
                    <th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('التاريخ') }}</th><th class="text-end">{{ __('الإجراءات') }}</th>
                </x-slot:head>
                @forelse($payments as $payment)
                    <tr>
                        <td><a href="{{ route('admin.payments.show', $payment) }}" class="font-monospace small ltr">{{ $payment->transaction_id }}</a></td>
                        <td>{{ AdminLabels::label(AdminLabels::PAYMENT_TYPES, $payment->payment_type) }}</td>
                        <td>
                            {{ AdminLabels::label(AdminLabels::PAYMENT_METHODS, $payment->method) }}
                            @if($payment->gateway)<div class="cell-sub ltr">{{ $payment->gateway }}</div>@endif
                        </td>
                        <td><x-admin.money :minor="(int) $payment->amount_minor" class="fw-bold" /></td>
                        <td>
                            <x-admin.status-pill domain="payment" :status="$payment->status" />
                            @if($payment->needs_review)<span class="pill pill-sm pill-warning"><i class="bi bi-exclamation-triangle-fill"></i>{{ __('مراجعة') }}</span>@endif
                        </td>
                        <td class="num">{{ $at($payment->created_at) }}</td>
                        <td class="text-end"><a href="{{ route('admin.payments.show', $payment) }}" class="icon-btn brand" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="7" icon="credit-card-2-front" title="لا توجد مدفوعات" text="لا توجد مدفوعات لهذا الرقم في هذه المنظمة." />
                @endforelse
            </x-admin.data-table>
            @if($totals['payments'] > $payments->count())
                <p class="text-muted small">{{ __('المعروض هنا أحدث :shown من أصل :total.', ['shown' => $payments->count(), 'total' => number_format($totals['payments'])]) }}</p>
            @endif
        @endif

        @if($can['orders'])
            <x-admin.data-table title="طلبات البوابات" icon="cart-check" :count="$totals['orders']" data-lookup-orders="{{ $orders->count() }}">
                <x-slot:head>
                    <th>{{ __('رقم الطلب') }}</th><th>{{ __('البوابة') }}</th><th>{{ __('الفعالية') }}</th>
                    <th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('التاريخ') }}</th><th class="text-end">{{ __('الإجراءات') }}</th>
                </x-slot:head>
                @forelse($orders as $order)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('admin.gates.sales.show', $order) }}" class="font-monospace small ltr">{{ $order->reference }}</a></td>
                        <td>{{ $order->gate ? $order->gate->code.' · '.$order->gate->localizedName() : __('بلا بوابة') }}</td>
                        <td>{{ $order->event?->title_ar ?: ($order->event?->title ?? '—') }}</td>
                        <td><x-admin.money :minor="(int) $order->total_minor" class="fw-bold" /></td>
                        <td><x-admin.status-pill :variant="$orderVariant[$order->status] ?? 'neutral'" :label="$orderLabel[$order->status] ?? $order->status" /></td>
                        <td class="num">{{ $at($order->created_at) }}</td>
                        <td class="text-end"><a href="{{ route('admin.gates.sales.show', $order) }}" class="icon-btn brand" title="{{ __('عرض') }}" aria-label="{{ __('عرض') }}"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="7" icon="cart-check" title="لا توجد طلبات" text="لا توجد طلبات بوابات لهذا الرقم في هذه المنظمة." />
                @endforelse
            </x-admin.data-table>
            @if($totals['orders'] > $orders->count())
                <p class="text-muted small">{{ __('المعروض هنا أحدث :shown من أصل :total.', ['shown' => $orders->count(), 'total' => number_format($totals['orders'])]) }}</p>
            @endif
        @endif
    @endif
@endsection
