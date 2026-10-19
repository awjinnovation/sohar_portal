@extends('layouts.admin')

@section('title', 'أجهزة المسح')
@section('page-title', 'أجهزة المسح')
@section('page-subtitle', 'حالة الأجهزة المُقرَنة بالبوابات وآخر ظهور لها وبطاريتها وطابور مزامنتها')
@php
    $pageIcon = 'upc-scan';
    $breadcrumbs = ['البوابات' => route('admin.gates.index'), 'أجهزة المسح' => null];
    $L = \App\Services\Gate\GateLabels::class;
    $gateOptions = $gates->mapWithKeys(fn ($g) => [$g->id => $g->code.' · '.$g->localizedName()])->all();
    $typeIcons = ['android_pos' => 'credit-card-2-back', 'handheld' => 'upc-scan', 'phone' => 'phone', 'tablet' => 'tablet', 'web' => 'laptop'];
@endphp
@section('page-actions')
    @if($gates->isNotEmpty())
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pairModal"><i class="bi bi-qr-code"></i> {{ __('إقران جهاز جديد') }}</button>
    @else
        <a href="{{ route('admin.gates.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('أضف بوابة أولًا') }}</a>
    @endif
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="الأجهزة" icon="upc-scan" :count="$devices->total()" :paginator="$devices">
        <x-slot:filters>
            <select name="gate" class="form-select" aria-label="{{ __('البوابة') }}">
                <option value="">{{ __('كل البوابات') }}</option>
                @foreach($gateOptions as $gid => $glabel)
                    <option value="{{ $gid }}" @selected((string) request('gate') === (string) $gid)>{{ $glabel }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" aria-label="{{ __('الحالة') }}">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('نشط') }}</option>
                <option value="revoked" @selected($status === 'revoked')>{{ __('ملغى') }}</option>
            </select>
        </x-slot:filters>
        <x-slot:head>
            <th>{{ __('الجهاز') }}</th>
            <th>{{ __('البوابة') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('آخر ظهور') }}</th>
            <th>{{ __('البطارية') }}</th>
            <th>{{ __('طابور المزامنة') }}</th>
            <th>{{ __('الإصدار') }}</th>
            <th>{{ __('الموظف') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($devices as $device)
            @php
                $conn = $device->connectivity();
                [$connLabel, $connVariant] = $L::CONNECTIVITY[$conn];
            @endphp
            <tr>
                <td>
                    <div class="cell-media">
                        <span class="ph"><i class="bi bi-{{ $typeIcons[$device->device_type] ?? 'upc-scan' }}"></i></span>
                        <div>
                            <span class="cell-title d-block">{{ $device->name }}</span>
                            <div class="cell-sub">{{ $L::label($L::DEVICE_TYPES, $device->device_type) }}@if($device->model) · <span class="ltr">{{ $device->model }}</span>@endif @if($device->scanner_source) · {{ $L::label($L::SOURCES, $device->scanner_source) }}@endif</div>
                        </div>
                    </div>
                </td>
                <td class="small">{{ $device->gate ? $device->gate->code.' · '.$device->gate->localizedName() : '—' }}</td>
                <td><x-admin.status-pill :variant="$connVariant" :label="$connLabel" /></td>
                <td class="small text-muted">{{ $device->last_seen_at?->locale(app()->getLocale())->diffForHumans() ?? '—' }}</td>
                <td class="num">
                    @if($device->battery !== null)
                        <span class="{{ $device->battery <= 20 ? 'text-danger fw-bold' : '' }}"><i class="bi bi-battery{{ $device->battery >= 75 ? '-full' : ($device->battery >= 35 ? '-half' : '') }}"></i> {{ $device->battery }}%</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="num">
                    @if($device->queue_size > 0)
                        <span class="pill pill-warning pill-sm"><i class="bi bi-cloud-upload"></i>{{ number_format($device->queue_size) }}</span>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
                <td class="small ltr text-start">{{ $device->app_version ?: '—' }}<div class="cell-sub">{{ $device->platform }}</div></td>
                <td class="small">{{ $device->staff?->name ?? '—' }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.gates.scans.index', ['device' => $device->id]) }}" class="icon-btn" title="{{ __('سجل المسح') }}"><i class="bi bi-list-check"></i></a>
                        @unless($device->isRevoked())
                            <button type="button" class="icon-btn brand" title="{{ __('تعديل') }}" data-bs-toggle="modal" data-bs-target="#deviceModal{{ $device->id }}"><i class="bi bi-pencil"></i></button>
                            <form action="{{ route('admin.gates.devices.revoke', $device) }}" method="POST"
                                  data-confirm="{{ __('سيتوقف الجهاز «:name» عن المسح فورًا، ولن يعمل حتى يُقرَن من جديد.', ['name' => $device->name]) }}" data-confirm-ok="{{ __('إلغاء الجهاز') }}">
                                @csrf
                                <button type="submit" class="icon-btn danger" title="{{ __('إلغاء الجهاز') }}"><i class="bi bi-slash-circle"></i></button>
                            </form>
                        @endunless
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="9" icon="upc-scan" title="لا توجد أجهزة مُقرَنة" text="اضغط «إقران جهاز جديد» ثم امسح الرمز من تطبيق البوابة.">
                @if($gates->isNotEmpty())
                    <button type="button" class="btn btn-tonal" data-bs-toggle="modal" data-bs-target="#pairModal"><i class="bi bi-qr-code"></i> {{ __('إقران جهاز جديد') }}</button>
                @endif
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>

    {{-- Edit device modals --}}
    @foreach($devices as $device)
        @unless($device->isRevoked())
            <div class="modal fade" id="deviceModal{{ $device->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('admin.gates.devices.update', $device) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 fw-bold">{{ __('تعديل الجهاز') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('إغلاق') }}"></button>
                        </div>
                        <div class="modal-body">
                            <x-admin.form-field name="name" :id="'dn'.$device->id" label="اسم الجهاز" :value="$device->name" required />
                            <x-admin.form-field name="gate_id" :id="'dg'.$device->id" label="البوابة" type="select" placeholder="بلا ربط" :value="(string) $device->gate_id" :options="$gateOptions" />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endunless
    @endforeach

    {{-- Pair new device --}}
    <div class="modal fade" id="pairModal" tabindex="-1" aria-labelledby="pairModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 fw-bold d-flex align-items-center gap-2" id="pairModalTitle">
                        <span class="stat-icon tone-brand" style="width:36px;height:36px;font-size:17px"><i class="bi bi-qr-code"></i></span>
                        {{ __('إقران جهاز جديد') }}
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('إغلاق') }}"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('admin.gates.devices.pair') }}" id="pairForm" class="pair-step" @if($pairing) hidden @endif>
                        @csrf
                        <x-admin.form-field name="gate_id" id="pair_gate" label="البوابة" type="select" :value="(string) (request('gate') ?: $gates->first()?->id)" :options="$gateOptions" required
                            help="سيُربط الجهاز بهذه البوابة ويمكن تغييرها لاحقًا" />
                        <ol class="small text-muted ps-3 mb-0">
                            <li>{{ __('افتح تطبيق «موسم للبوابات» على الجهاز') }}</li>
                            <li>{{ __('اختر «إقران» وامسح رمز QR أو أدخل الرمز المكوّن من 6 أحرف') }}</li>
                            <li>{{ __('الرمز صالح 10 دقائق ولمرة واحدة') }}</li>
                        </ol>
                        <button type="submit" class="btn btn-primary w-100 mt-3" data-busy><i class="bi bi-key"></i> {{ __('إنشاء رمز الإقران') }}</button>
                    </form>

                    <div id="pairResult" class="pair-result" @unless($pairing) hidden @endunless>
                        <div class="pair-qr" id="pairQr">{!! $pairing['qr_svg'] ?? '' !!}</div>
                        <div class="pair-code ltr" id="pairCode" aria-label="{{ __('رمز الإقران') }}">{{ $pairing['code'] ?? '' }}</div>
                        <div class="text-center small text-muted">
                            <span id="pairGate">{{ $pairing['gate'] ?? '' }}</span> ·
                            <span id="pairExpiry" data-expires="{{ $pairing['expires_at'] ?? '' }}"></span>
                        </div>
                        <details class="mt-3 small">
                            <summary class="text-muted">{{ __('رابط الإقران') }}</summary>
                            <code class="d-block mt-2 ltr text-break" id="pairLink">{{ $pairing['link'] ?? '' }}</code>
                        </details>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('إغلاق') }}</button>
                    <button type="button" class="btn btn-tonal" id="pairAgain" @unless($pairing) hidden @endunless><i class="bi bi-arrow-repeat"></i> {{ __('رمز جديد') }}</button>
                    <a href="{{ route('admin.gates.devices.index') }}" class="btn btn-primary" id="pairDone" @unless($pairing) hidden @endunless>{{ __('تم، حدِّث القائمة') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .pair-result { text-align: center; }
    .pair-qr { display: inline-block; padding: 12px; background: #fff; border-radius: var(--r-lg); border: 1px solid var(--hairline); line-height: 0; }
    .pair-qr svg { width: 220px; height: 220px; }
    .pair-code { font-family: var(--font-num), ui-monospace, monospace; font-size: 40px; font-weight: 800; letter-spacing: .28em; margin: 14px 0 4px; padding-inline-start: .28em; color: var(--text); }
    .pair-expired .pair-qr, .pair-expired .pair-code { opacity: .3; filter: grayscale(1); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('pairModal');
    if (!modalEl) return;
    var form = document.getElementById('pairForm');
    var result = document.getElementById('pairResult');
    var again = document.getElementById('pairAgain');
    var done = document.getElementById('pairDone');
    var expiry = document.getElementById('pairExpiry');
    var tpl = { left: @json(__('ينتهي خلال :time')), expired: @json(__('انتهت صلاحية الرمز')), failed: @json(__('تعذّر إنشاء الرمز، حاول مرة أخرى')) };
    var timer = null;

    function tick() {
        var at = Date.parse(expiry.dataset.expires || '');
        if (!at) { expiry.textContent = ''; return; }
        var s = Math.max(0, Math.round((at - Date.now()) / 1000));
        if (s <= 0) { expiry.textContent = tpl.expired; result.classList.add('pair-expired'); clearInterval(timer); return; }
        var mm = Math.floor(s / 60), ss = ('0' + (s % 60)).slice(-2);
        expiry.textContent = tpl.left.replace(':time', mm + ':' + ss);
    }
    function show(p) {
        document.getElementById('pairQr').innerHTML = p.qr_svg;
        document.getElementById('pairCode').textContent = p.code;
        document.getElementById('pairGate').textContent = p.gate || '';
        document.getElementById('pairLink').textContent = p.link;
        expiry.dataset.expires = p.expires_at;
        result.classList.remove('pair-expired');
        form.hidden = true; result.hidden = false; again.hidden = false; done.hidden = false;
        clearInterval(timer); tick(); timer = setInterval(tick, 1000);
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('[type=submit]');
        btn.disabled = true;
        fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form), credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (j) { show(j.data); })
            .catch(function () { alert(tpl.failed); })
            .finally(function () { btn.disabled = false; });
    });
    again.addEventListener('click', function () { form.hidden = false; result.hidden = true; again.hidden = true; });
    @if($pairing)
        tick(); timer = setInterval(tick, 1000);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    @endif
})();
</script>
@endpush
