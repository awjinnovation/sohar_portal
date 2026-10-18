{{-- Data table card: toolbar (title, search, filters, actions), table, empty state, pagination.
     <x-admin.data-table title="…" :count="$items->total()" search="q" search-placeholder="…" :paginator="$items">
        <x-slot:filters>…selects (inside the same GET form)…</x-slot:filters>
        <x-slot:actions>…</x-slot:actions>
        <x-slot:head><th>…</th></x-slot:head>
        …<tr> rows (use @forelse … @empty <x-admin.empty-row :colspan="5" /> )…
     </x-admin.data-table> --}}
@props(['title' => null, 'icon' => null, 'count' => null, 'search' => null, 'searchPlaceholder' => 'بحث…', 'paginator' => null, 'preserve' => []])
@php
    $hasToolbar = $title || $search || isset($filters) || isset($actions);
    $keep = collect(request()->query())->except(array_values(array_filter([$search, 'page'])))->all();
@endphp
<section {{ $attributes->merge(['class' => 'x-card dt']) }}>
    @if($hasToolbar)
        <form method="GET" class="dt-toolbar" role="search">
            @if($title)<div class="dt-title">@if($icon)<i class="bi bi-{{ $icon }} text-primary"></i>@endif{{ __($title) }}@if($count !== null)<span class="count">{{ number_format($count) }}</span>@endif</div>@endif
            @if($search)
                <div class="dt-search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="{{ $search }}" value="{{ request($search) }}" class="form-control" placeholder="{{ __($searchPlaceholder) }}" aria-label="{{ __($searchPlaceholder) }}">
                </div>
            @endif
            @isset($filters)<div class="dt-filters">{{ $filters }}</div>@endisset
            @if($search || isset($filters))
                <button class="btn btn-tonal" type="submit"><i class="bi bi-funnel"></i><span class="d-none d-sm-inline">{{ __('تطبيق') }}</span></button>
                @if(collect(request()->query())->except('page')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
                    <a href="{{ url()->current() }}" class="btn btn-link btn-sm">{{ __('مسح') }}</a>
                @endif
            @endif
            @isset($actions)<div class="d-flex gap-2 flex-wrap ms-auto">{{ $actions }}</div>@endisset
        </form>
    @endif
    <div class="x-table-wrap table-responsive">
        <table class="table table-hover align-middle">
            @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
    @if($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
        <div class="dt-foot">{{ $paginator->withQueryString()->links() }}</div>
    @endif
</section>
