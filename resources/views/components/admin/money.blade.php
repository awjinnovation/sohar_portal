{{-- Money (§3.2.3): <x-admin.money :minor="12450" /> or :amount="'12.450'" ; signed → +/− ; tone=credit|debit|muted ; size=lg|hero --}}
@props(['minor' => null, 'amount' => null, 'currency' => null, 'decimals' => null, 'signed' => false, 'tone' => null, 'size' => null, 'direction' => null])
@php
    $mDecimals = $decimals ?? \App\View\Fmt::decimals();
    $mMinor = $minor !== null ? (int) $minor : \App\View\Fmt::toMinor($amount, $mDecimals);
    if ($direction === 'debit' && $mMinor > 0) { $mMinor = -$mMinor; }
    $mSigned = $signed || $direction !== null;
    $mSign = $mMinor < 0 ? "\u{2212}" : ($mSigned && $mMinor > 0 ? '+' : '');
    $mTone = $tone ?? ($mSigned ? ($mMinor > 0 ? 'credit' : 'debit') : null);
@endphp
<span {{ $attributes->merge(['class' => trim('money '.($mTone ?? '').' '.($size ? 'money-'.$size : ''))]) }} dir="ltr">{{ $mSign }}<span class="cur">{{ $currency ?? \App\View\Fmt::currency() }}</span>{{ \App\View\Fmt::number($mMinor, $mDecimals) }}</span>
