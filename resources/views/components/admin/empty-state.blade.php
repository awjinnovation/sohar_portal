{{-- <x-admin.empty-state icon="inbox" title="…" text="…"> optional action </x-admin.empty-state> --}}
@props(['icon' => 'inbox', 'title' => 'لا توجد بيانات', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <div class="ico"><i class="bi bi-{{ $icon }}"></i></div>
    <h3>{{ __($title) }}</h3>
    @if($text)<p>{{ __($text) }}</p>@endif
    {{ $slot }}
</div>
