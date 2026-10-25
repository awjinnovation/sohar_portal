{{-- <x-admin.card title="…" icon="wallet2" subtitle="…" :flush="true"> body <x-slot:actions>…</x-slot:actions> <x-slot:footer>…</x-slot:footer></x-admin.card> --}}
@props(['title' => null, 'icon' => null, 'subtitle' => null, 'flush' => false, 'id' => null])
<section {{ $attributes->merge(['class' => 'x-card']) }} @if($id) id="{{ $id }}" @endif>
    @if($title || isset($actions))
        <header class="x-card-head">
            <div class="t">
                @if($title)<h2>@if($icon)<i class="bi bi-{{ $icon }}"></i>@endif{{ __($title) }}</h2>@endif
                @if($subtitle)<p>{{ __($subtitle) }}</p>@endif
            </div>
            @isset($actions)<div class="d-flex flex-wrap gap-2 align-items-center">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="x-card-body {{ $flush ? 'flush' : '' }}">{{ $slot }}</div>
    @isset($footer)<footer class="x-card-foot">{{ $footer }}</footer>@endisset
</section>
