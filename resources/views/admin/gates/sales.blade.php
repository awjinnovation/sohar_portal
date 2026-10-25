@extends('layouts.admin')

@section('title', 'مبيعات البوابات')
@section('page-title', 'مبيعات البوابات')
@section('page-subtitle', 'التذاكر التي يبيعها موظفو البوابات ويدفع الزائر ثمنها عبر رابط الدفع')
@php
    $pageIcon = 'cart-check';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), 'مبيعات البوابات' => null];
    $tz = \App\Support\TenantTime::zone($brand ?? null);
    $statusVariant = ['pending' => 'warning', 'paid' => 'success', 'expired' => 'neutral', 'cancelled' => 'error', 'paid_unfulfilled' => 'error', 'refunded' => 'neutral'];
    $statusLabel = ['pending' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'expired' => 'منتهي الصلاحية', 'cancelled' => 'ملغى', 'paid_unfulfilled' => 'بحاجة إلى استرداد', 'refunded' => 'مسترد'];
@endphp
@section('page-actions')
    @can('manage settings')
        <a href="{{ route('admin.branding.edit') }}#sec-gate-sales" class="btn"><i class="bi bi-sliders"></i> {{ __('إعدادات البيع') }}</a>
    @endcan
    <a href="{{ route('admin.gates.dashboard') }}" class="btn"><i class="bi bi-broadcast"></i> {{ __('لوحة البوابات المباشرة') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    @unless($settings['enabled'])
        <div class="alert alert-warning d-flex gap-2 align-items-start"><i class="bi bi-info-circle-fill"></i>
            <span>{{ __('بيع التذاكر عند البوابات غير مفعّل لدى هذه المنظمة. فعّله من الهوية والإعدادات، ثم اختر البوابات التي تبيع التذاكر.') }}</span></div>
    @endunless

    @if($refundDue > 0)
        <div class="alert alert-danger d-flex gap-2 align-items-center" data-refund-due="{{ $refundDue }}"><i class="bi bi-exclamation-octagon-fill"></i>
            <span class="flex-grow-1">{{ __('طلبات دُفعت بعد انتهاء صلاحيتها ونفدت تذاكرها، فلم تصدر لها تذاكر: :count. استردّ مبالغها.', ['count' => $refundDue]) }}</span>
            <a href="{{ route('admin.gates.sales.index', ['date' => 'all', 'status' => 'paid_unfulfilled']) }}" class="btn btn-sm btn-outline-danger">{{ __('عرض الطلبات') }}</a></div>
    @endif

    <div class="stat-grid">
        <x-admin.stat-tile label="إجمالي المبيعات" :money="$summary['paid_minor']" icon="cash-coin" tone="success" :hint="$date ? __('يوم :date', ['date' => $date]) : __('كل الأيام')" />
        <x-admin.stat-tile label="الطلبات المدفوعة" :value="$summary['paid_count']" icon="receipt" tone="brand" :hint="__('الطلبات كلها: :count', ['count' => number_format($summary['count'])])" />
        <x-admin.stat-tile label="التذاكر المبيعة" :value="$summary['tickets']" icon="ticket-perforated" tone="info" />
        <x-admin.stat-tile label="بانتظار الدفع" :value="$summary['pending_count']" icon="hourglass-split" tone="warning"
            :href="route('admin.gates.sales.index', array_merge(request()->query(), ['status' => 'pending']))" />
    </div>

    <div class="row g-4 mb-4">
        @foreach([['perGate', 'حسب البوابة', 'door-open', 'البوابة'], ['perStaff', 'حسب الموظف', 'person-badge', 'الموظف']] as [$var, $title, $icon, $col])
            <div class="col-xl-6">
                <x-admin.card :title="$title" :icon="$icon" :flush="true">
                    <div class="x-table-wrap table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>{{ __($col) }}</th><th class="text-end">{{ __('عدد الطلبات') }}</th><th class="text-end">{{ __('المدفوعة') }}</th><th class="text-end">{{ __('التذاكر') }}</th><th class="text-end">{{ __('المبلغ') }}</th></tr></thead>
                            <tbody>
                            @forelse($$var as $row)
                                <tr data-total-row="{{ $var }}">
                                    <td class="cell-title">
                                        @if($var === 'perGate')
                                            {{ ($g = $gateNames[$row->k] ?? null) ? $g->code.' · '.$g->localizedName() : __('بلا بوابة') }}
                                        @else
                                            {{ $staff->firstWhere('id', $row->k)?->name ?? __('غير معروف') }}
                                        @endif
                                    </td>
                                    <td class="num text-end">{{ number_format($row->orders) }}</td>
                                    <td class="num text-end">{{ number_format($row->paid) }}</td>
                                    <td class="num text-end">{{ number_format($row->tickets) }}</td>
                                    <td class="text-end"><x-admin.money :minor="(int) $row->paid_minor" class="fw-bold" /></td>
                                </tr>
                            @empty
                                <x-admin.empty-row :colspan="5" icon="cart" title="لا توجد مبيعات" />
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-admin.card>
            </div>
        @endforeach
    </div>

    <x-admin.data-table title="طلبات البوابات" icon="cart-check" :count="$orders->total()" search="q" search-placeholder="رقم الطلب أو هاتف المشتري" :paginator="$orders">
        <x-slot:filters>
            <input type="date" name="date" value="{{ $date }}" class="form-control" aria-label="{{ __('اليوم') }}">
            <select name="gate" class="form-select" aria-label="{{ __('البوابة') }}">
                <option value="">{{ __('كل البوابات') }}</option>
                @foreach($gates as $g)
                    <option value="{{ $g->id }}" @selected((string) request('gate') === (string) $g->id)>{{ $g->code }} · {{ $g->localizedName() }}</option>
                @endforeach
            </select>
            <select name="staff" class="form-select" aria-label="{{ __('الموظف') }}">
                <option value="">{{ __('كل الموظفين') }}</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}" @selected((string) request('staff') === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach($statusLabel as $sk => $sl)
                    <option value="{{ $sk }}" @selected(request('status') === $sk)>{{ __($sl) }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:actions>
            <a href="{{ route('admin.gates.sales.index', ['date' => 'all']) }}" class="btn btn-link btn-sm">{{ __('كل الأيام') }}</a>
        </x-slot:actions>
        <x-slot:head>
            <th>{{ __('رقم الطلب') }}</th>
            <th>{{ __('الفعالية') }}</th>
            <th>{{ __('التذاكر') }}</th>
            <th>{{ __('المشتري') }}</th>
            <th>{{ __('البوابة') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('الوقت') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($orders as $order)
            <tr data-order="{{ $order->reference }}">
                <td class="text-nowrap"><a href="{{ route('admin.gates.sales.show', $order) }}" class="font-monospace small ltr">{{ $order->reference }}</a></td>
                <td class="cell-title">{{ $order->event ? (app()->getLocale() === 'ar' ? ($order->event->title_ar ?: $order->event->title) : $order->event->title) : '—' }}
                    <div class="cell-sub num">{{ $order->booking_date?->format('Y-m-d') }}</div></td>
                <td class="small">
                    @foreach($order->lines as $line)
                        <div class="text-nowrap"><span class="num">{{ $line->quantity }}</span> × {{ \App\Models\EventTicketType::pickLocale($line->name) ?: $line->key }}</div>
                    @endforeach
                </td>
                <td>
                    <div class="cell-title">{{ $order->buyer_name ?: '—' }}</div>
                    <div class="cell-sub ltr text-start num text-nowrap">{{ $order->maskedPhone($brand ?? null) }}</div>
                </td>
                <td class="small"><span class="text-nowrap">{{ $order->gate ? $order->gate->localizedName() : '—' }}</span>
                    <div class="cell-sub">{{ $order->staff?->name }}</div></td>
                <td><x-admin.money :minor="(int) $order->total_minor" class="fw-bold" /></td>
                <td><x-admin.status-pill :variant="$statusVariant[$order->status] ?? 'neutral'" :label="$statusLabel[$order->status] ?? $order->status" /></td>
                <td class="num small text-muted text-nowrap"><span class="ltr">{{ $order->created_at->copy()->setTimezone($tz)->format('Y-m-d H:i') }}</span></td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.gates.sales.show', $order) }}" class="icon-btn brand" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="9" icon="cart-check" title="لا توجد طلبات بيع" text="تظهر هنا الطلبات التي يُنشئها موظفو البوابات." />
        @endforelse
    </x-admin.data-table>
@endsection
