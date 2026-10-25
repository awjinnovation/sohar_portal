{{-- <x-admin.stat-tile label="…" :value="12" icon="ticket" tone="brand|accent|success|warning|error|info|points|neutral" hint="…" href="…" :money="1234" /> --}}
@props(['label', 'value' => null, 'money' => null, 'icon' => 'graph-up', 'tone' => 'brand', 'hint' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat']) }}>
    <div class="stat-top">
        <span class="stat-icon tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
        <span class="stat-label">{{ __($label) }}</span>
    </div>
    <div class="stat-value">
        @if($money !== null)
            <x-admin.money :minor="(int) $money" />
        @else
            {{ is_numeric($value) ? number_format($value) : $value }}
        @endif
    </div>
    @if($hint || $slot->isNotEmpty())
        <div class="stat-hint">{{ __($hint) }}{{ $slot }}</div>
    @endif
</{{ $tag }}>
