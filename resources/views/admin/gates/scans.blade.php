@extends('layouts.admin')

@section('title', 'سجل المسح')
@section('page-title', 'سجل المسح')
@section('page-subtitle', 'كل عمليات المسح على البوابات، في حال الاتصال وانقطاعه')
@php
    $pageIcon = 'list-check';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), 'سجل المسح' => null];
    $L = \App\Services\Gate\GateLabels::class;
    $tz = \App\Support\TenantTime::zone($brand ?? null);
@endphp
@section('page-actions')
    <a href="{{ route('admin.gates.dashboard') }}" class="btn"><i class="bi bi-broadcast"></i> {{ __('لوحة البوابات المباشرة') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="عمليات المسح" icon="list-check" :count="$scans->total()" search="q" search-placeholder="رقم التذكرة" :paginator="$scans">
        <x-slot:filters>
            <input type="date" name="date" value="{{ $date }}" class="form-control" aria-label="{{ __('اليوم') }}">
            <select name="gate" class="form-select" aria-label="{{ __('البوابة') }}">
                <option value="">{{ __('كل البوابات') }}</option>
                @foreach($gates as $g)
                    <option value="{{ $g->id }}" @selected((string) request('gate') === (string) $g->id)>{{ $g->code }} · {{ $g->localizedName() }}</option>
                @endforeach
                <option value="none" @selected(request('gate') === 'none')>{{ __('تطبيق الموظفين (بلا بوابة)') }}</option>
            </select>
            <select name="device" class="form-select" aria-label="{{ __('الجهاز') }}">
                <option value="">{{ __('كل الأجهزة') }}</option>
                @foreach($devices as $d)
                    <option value="{{ $d->id }}" @selected((string) request('device') === (string) $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            <select name="result" class="form-select" aria-label="{{ __('النتيجة') }}">
                <option value="">{{ __('كل النتائج') }}</option>
                <option value="rejects" @selected(request('result') === 'rejects')>{{ __('المرفوضة فقط') }}</option>
                @foreach($L::RESULTS as $rk => $rv)
                    <option value="{{ $rk }}" @selected(request('result') === $rk)>{{ __($rv[0]) }}</option>
                @endforeach
            </select>
            <select name="direction" class="form-select" aria-label="{{ __('الاتجاه') }}">
                <option value="">{{ __('الاتجاهان') }}</option>
                @foreach($L::SCAN_DIRECTIONS as $dk => $dv)
                    <option value="{{ $dk }}" @selected(request('direction') === $dk)>{{ __($dv) }}</option>
                @endforeach
            </select>
            <select name="source" class="form-select" aria-label="{{ __('المصدر') }}">
                <option value="">{{ __('كل المصادر') }}</option>
                @foreach($L::SOURCES as $sk => $sv)
                    <option value="{{ $sk }}" @selected(request('source') === $sk)>{{ __($sv) }}</option>
                @endforeach
            </select>
            <select name="flag" class="form-select" aria-label="{{ __('تصفية') }}">
                <option value="">{{ __('الكل') }}</option>
                <option value="offline" @selected(request('flag') === 'offline')>{{ __('دون اتصال') }}</option>
                <option value="conflict" @selected(request('flag') === 'conflict')>{{ __('تعارضات') }}</option>
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th>{{ __('الوقت') }}</th>
            <th>{{ __('النتيجة') }}</th>
            <th>{{ __('التذكرة') }}</th>
            <th>{{ __('البوابة') }}</th>
            <th>{{ __('الجهاز') }}</th>
            <th>{{ __('المصدر') }}</th>
            <th>{{ __('الموظف') }}</th>
        </x-slot:head>

        @forelse($scans as $scan)
            <tr>
                <td class="num small">
                    {{ $scan->scanned_at->copy()->setTimezone($tz)->format('Y-m-d H:i:s') }}
                    @if($scan->offline)<div class="cell-sub"><i class="bi bi-cloud-slash"></i> {{ __('دون اتصال') }} · {{ __('وصل') }} {{ $scan->received_at->copy()->setTimezone($tz)->format('H:i') }}</div>@endif
                </td>
                <td>
                    <x-admin.status-pill :variant="$scan->result === 'valid' && $scan->direction === 'out' ? 'neutral' : $L::resultVariant($scan->result)" :label="$L::result($scan->result, $scan->direction)" />
                    @if($scan->conflict)<span class="pill pill-error pill-sm" title="{{ __('دخلت التذكرة من مكان آخر أولًا') }}"><i class="bi bi-exclamation-diamond"></i>{{ __('تعارض') }}</span>@endif
                </td>
                <td>
                    @if($scan->ticket)
                        <a href="{{ \Illuminate\Support\Facades\Route::has('admin.tickets.show') && auth()->user()->can('manage tickets') ? route('admin.tickets.show', $scan->ticket) : '#' }}" class="cell-title ltr d-block text-start">{{ $scan->ticket->ticket_number }}</a>
                        <div class="cell-sub">{{ $scan->ticket->ticket_type }} · {{ $scan->ticket->event ? (app()->getLocale() === 'ar' ? ($scan->ticket->event->title_ar ?: $scan->ticket->event->title) : $scan->ticket->event->title) : '' }}</div>
                    @else
                        <span class="text-muted small ltr" title="{{ $scan->code_hash }}">#{{ substr($scan->code_hash, 0, 10) }}…</span>
                    @endif
                </td>
                <td class="small">{{ $scan->gate ? $scan->gate->code.' · '.$scan->gate->localizedName() : __('تطبيق الموظفين') }}
                    <div class="cell-sub">{{ $L::label($L::SCAN_DIRECTIONS, $scan->direction) }}</div></td>
                <td class="small">{{ $scan->device?->name ?? '—' }}</td>
                <td class="small">{{ $L::label($L::SOURCES, $scan->source) }}</td>
                <td class="small">{{ $scan->staff?->name ?? '—' }}</td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="7" icon="list-check" title="لا توجد عمليات مسح" text="جرّب تغيير عوامل التصفية." />
        @endforelse
    </x-admin.data-table>
@endsection
