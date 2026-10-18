{{-- Platform status vocabulary (§4.14).
     <x-admin.status-pill domain="payment|refund|deposit|ticket|wallet|tx|active|announcement" :status="$x->status" />
     or explicit: <x-admin.status-pill variant="success" label="…" icon="check" /> --}}
@props(['domain' => null, 'status' => null, 'variant' => null, 'label' => null, 'icon' => null, 'size' => null])
@php
    $L = \App\Support\AdminLabels::class;
    $vocab = [
        'payment' => ['completed' => 'success', 'pending' => 'warning', 'processing' => 'info', 'failed' => 'error', 'cancelled' => 'neutral', 'refunded' => 'neutral'],
        'tx' => ['completed' => 'success', 'pending' => 'warning', 'reversed' => 'neutral', 'failed' => 'error'],
        'refund' => ['pending' => 'warning', 'approved' => 'info', 'paid' => 'success', 'rejected' => 'error'],
        'deposit' => ['held' => 'info', 'released' => 'success', 'forfeited' => 'neutral'],
        'ticket' => ['active' => 'success', 'valid' => 'success', 'used' => 'neutral', 'expired' => 'neutral', 'cancelled' => 'error', 'refunded' => 'error'],
        'wallet' => ['active' => 'success', 'frozen' => 'error'],
        'tenant' => ['active' => 'success', 'inactive' => 'neutral'],
        'announcement' => ['info' => 'info', 'warning' => 'warning', 'emergency' => 'error', 'critical' => 'error', 'celebration' => 'brand', 'promo' => 'brand'],
    ];
    $labels = [
        'payment' => $L::PAYMENT_STATUSES, 'tx' => $L::TX_STATUSES, 'refund' => $L::REFUND_STATUSES,
        'deposit' => $L::DEPOSIT_STATUSES, 'wallet' => $L::WALLET_STATUSES,
        'ticket' => ['active' => ['صالحة'], 'valid' => ['صالحة'], 'used' => ['مستخدمة'], 'expired' => ['منتهية'], 'cancelled' => ['ملغاة'], 'refunded' => ['مستردة']],
        'tenant' => ['active' => ['نشطة'], 'inactive' => ['غير نشطة']],
        'announcement' => ['info' => ['معلومات'], 'warning' => ['تحذير'], 'emergency' => ['طوارئ'], 'critical' => ['عاجل'], 'celebration' => ['احتفال'], 'promo' => ['ترويجي']],
    ];
    $icons = ['success' => 'check-circle-fill', 'warning' => 'exclamation-triangle-fill', 'error' => 'x-circle-fill', 'info' => 'info-circle-fill'];
    $key = is_bool($status) ? ($status ? 'on' : 'off') : (string) $status;
    if ($domain === 'active') {
        $pv = $status ? 'success' : 'neutral';
        $pl = $label ?? ($status ? 'نشط' : 'غير نشط');
    } else {
        $pv = $variant ?? ($vocab[$domain][$key] ?? 'neutral');
        $pl = $label ?? (isset($labels[$domain][$key]) ? (array) $labels[$domain][$key] : [$key ?: '-'])[0];
    }
    $pi = $icon ?? (in_array($pv, ['warning', 'error'], true) ? $icons[$pv] : null);
@endphp
<span {{ $attributes->merge(['class' => 'pill pill-'.$pv.($size === 'sm' ? ' pill-sm' : '')]) }}>@if($pi)<i class="bi bi-{{ $pi }}"></i>@else<span class="dot"></span>@endif{{ __((string) $pl) }}</span>
