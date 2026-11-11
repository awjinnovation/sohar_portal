{{-- Card terminal receipts of a gate sale (docs/gate_sales_contract.md §9), newest first. --}}
@php
    $verdictVariant = ['verified' => 'success', 'review' => 'warning', 'rejected' => 'error'];
    $decisionLabel = ['approved' => 'اعتمده المشرف', 'cleared' => 'راجعه المشرف', 'voided' => 'ألغى المشرف العملية'];
    $canApprove = in_array($order->status, ['pending', 'expired'], true);
@endphp
<x-admin.card title="إيصالات جهاز البطاقة" icon="receipt-cutoff" :subtitle="__('يقرأ النظام كل صورة ويطابقها مع الطلب ومع الإيصالات المقبولة من قبل.')">
    @forelse($order->receipts->sortByDesc('id') as $receipt)
        <div class="d-flex flex-wrap gap-3 py-3 {{ $loop->last ? '' : 'border-bottom' }}" data-receipt="{{ $receipt->id }}" data-verdict="{{ $receipt->verdict }}">
            <a href="{{ route('admin.gates.sales.receipt-image', [$order, $receipt]) }}" target="_blank" rel="noopener" class="d-block flex-shrink-0">
                <img src="{{ route('admin.gates.sales.receipt-image', [$order, $receipt]) }}" alt="{{ __('صورة الإيصال') }}" loading="lazy"
                     class="rounded border" style="width: 120px; height: 160px; object-fit: cover; background: #f4f4f4">
            </a>
            <div class="flex-grow-1" style="min-width: 220px">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <x-admin.status-pill :variant="$verdictVariant[$receipt->verdict] ?? 'neutral'" :label="__('box_office.receipt_verdicts.'.$receipt->verdict)" />
                    @if($receipt->accepted)<x-admin.status-pill variant="success" :label="__('مقبول للطلب')" size="sm" />@endif
                    @if($receipt->decision)<x-admin.status-pill variant="neutral" :label="$decisionLabel[$receipt->decision] ?? $receipt->decision" size="sm" />@endif
                    <span class="text-muted small num"><span class="ltr">{{ $fmt($receipt->created_at) }}</span> · {{ $receipt->staff?->name ?? '—' }}</span>
                </div>
                @if($receipt->reasons)
                    <ul class="small mb-2 ps-3">
                        @foreach($receipt->reasons as $r)
                            <li class="{{ ($r['level'] ?? '') === 'reject' ? 'text-danger' : 'text-warning-emphasis' }}" data-reason="{{ $r['code'] ?? '' }}">
                                {{ __('box_office.receipt_reasons.'.($r['code'] ?? ''), (array) ($r['data'] ?? [])) }}
                                @if(($r['code'] ?? '') !== '' && in_array($r['code'], ['duplicate_image', 'duplicate_receipt'], true) && $receipt->duplicateOf)
                                    <a href="{{ route('admin.gates.sales.show', $receipt->duplicateOf) }}" class="ms-1">{{ __('عرض الطلب') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <dl class="dl dl-sm mb-0 small">
                    <dt>{{ __('المبلغ') }}</dt><dd class="num ltr text-start">{{ $receipt->amount_minor !== null ? ($brand?->formatMoney($receipt->amount_minor) ?? $receipt->amount_minor) : '—' }} {{ $receipt->extracted['currency'] ?? '' }}</dd>
                    <dt>{{ __('التاريخ') }}</dt><dd class="num ltr text-start">{{ $receipt->receipt_date?->toDateString() ?? '—' }} {{ $receipt->extracted['time'] ?? '' }}</dd>
                    <dt>{{ __('رقم الجهاز') }}</dt><dd class="font-monospace ltr text-start">{{ $receipt->terminal_id ?? '—' }}</dd>
                    <dt>{{ __('رمز الموافقة') }}</dt><dd class="font-monospace ltr text-start">{{ $receipt->approval_code ?? '—' }}</dd>
                    <dt>{{ __('الرقم المرجعي') }}</dt><dd class="font-monospace ltr text-start">{{ $receipt->rrn ?? '—' }}</dd>
                    <dt>{{ __('البطاقة') }}</dt><dd class="font-monospace ltr text-start">{{ trim(($receipt->extracted['card_scheme'] ?? '').' '.($receipt->card_last4 ? '•••• '.$receipt->card_last4 : '')) ?: '—' }}</dd>
                    @if($receipt->ai_status !== 'ok')
                        <dt>{{ __('الفحص الآلي') }}</dt><dd>{{ $receipt->ai_status === 'budget' ? __('بلغ حدّه الشهري') : __('غير متاح') }}</dd>
                    @endif
                    @if($receipt->decision_note)
                        <dt>{{ __('ملاحظة المشرف') }}</dt><dd>{{ $receipt->decision_note }} <span class="text-muted">· {{ $receipt->decider?->name }}</span></dd>
                    @endif
                </dl>
                @if($canApprove && ! $receipt->accepted)
                    <form method="POST" action="{{ route('admin.gates.sales.receipt-approve', [$order, $receipt]) }}" class="mt-2"
                          data-confirm="{{ __('تصدر التذاكر للمشتري ويُعدّ الطلب مدفوعًا بهذا الإيصال. اعتمد الإيصال فقط بعد التأكد من خصم المبلغ على جهاز البطاقة.') }}"
                          data-confirm-title="{{ __('اعتماد الإيصال') }}" data-confirm-ok="{{ __('اعتماد وإصدار التذاكر') }}">
                        @csrf
                        <div class="input-group input-group-sm">
                            <input type="text" name="note" class="form-control" maxlength="500" placeholder="{{ __('سبب الاعتماد (اختياري)') }}" aria-label="{{ __('سبب الاعتماد') }}">
                            <button type="submit" class="btn btn-success" data-busy><i class="bi bi-check2-circle"></i> {{ __('اعتماد الإيصال') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="text-muted mb-0">{{ __('لم يُرسل الموظف صورة إيصال بعد.') }}</p>
    @endforelse
</x-admin.card>
